<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify payment creation, PayPal Order orchestration, and RBAC access.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by payment management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Fake a successful PayPal OAuth2 token exchange and Order creation.
     */
    private function fakePayPalSuccess(string $orderId = 'ORDER-123'): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_ACCESS_TOKEN',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => $orderId,
                'links' => [
                    ['rel' => 'self', 'href' => "https://api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}"],
                    ['rel' => 'approve', 'href' => "https://www.sandbox.paypal.com/checkoutnow?token={$orderId}"],
                ],
            ], 201),
        ]);
    }

    /**
     * Verify a cashier can create a payment within the invoice's remaining balance.
     */
    public function test_cashier_can_create_payment_within_remaining_balance(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        $this->fakePayPalSuccess('ORDER-123');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 100000,
            'method' => 'paypal',
            'note' => 'Partial payment',
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Payment created',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'amount' => '100000.00',
                    'method' => 'paypal',
                    'status' => 'pending',
                    'provider' => 'paypal',
                    'provider_order_id' => 'ORDER-123',
                    'note' => 'Partial payment',
                    'approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-123',
        ]);
    }

    /**
     * Verify the VND invoice amount is converted to PayPal's currency before the
     * Order is created, since PayPal does not support VND as a transaction currency.
     */
    public function test_payment_amount_is_converted_to_paypal_currency(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        $this->fakePayPalSuccess('ORDER-123');

        $rate = (float) config('paypal.exchange_rate_vnd');
        $expectedCharged = round(100000 / $rate, 2);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 100000,
            'method' => 'paypal',
        ])->assertCreated()
            ->assertJson([
                'data' => [
                    // The invoice/payment amount on record always stays in VND —
                    // conversion only affects what's sent to PayPal, not what's stored.
                    'amount' => '100000.00',
                ],
            ]);

        Http::assertSent(
            fn ($request): bool => str_contains($request->url(), '/v2/checkout/orders')
                && data_get($request->data(), 'purchase_units.0.amount.currency_code') === 'USD'
                && data_get($request->data(), 'purchase_units.0.amount.value') === number_format($expectedCharged, 2, '.', ''),
        );

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 100000,
        ]);
    }

    /**
     * Verify an amount exceeding the invoice remaining balance is rejected without calling PayPal.
     */
    public function test_amount_exceeding_remaining_balance_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $this->fakePayPalSuccess();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 150000,
            'method' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Amount exceeds the invoice remaining balance of 100000.00.');

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    /**
     * Verify the remaining balance subtracts only completed payments, allowing a new payment
     * for the exact amount still owed.
     */
    public function test_amount_up_to_remaining_after_completed_payment_is_allowed(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        Payment::factory()->completed()->create(['invoice_id' => $invoice->id, 'amount' => 120000]);
        $this->fakePayPalSuccess('ORDER-456');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 80000,
            'method' => 'paypal',
        ])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 80000,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Verify the remaining balance also subtracts pending payments, blocking a second
     * checkout for the same balance while a first one is still outstanding.
     */
    public function test_amount_exceeding_remaining_balance_including_pending_payment_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 200000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->fakePayPalSuccess();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 200000,
            'method' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Amount exceeds the invoice remaining balance of 0.00.');

        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
    }

    /**
     * Verify payments cannot be created for an invoice that is not unpaid.
     */
    public function test_payment_rejected_when_invoice_is_not_unpaid(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_CANCELLED]);
        $this->fakePayPalSuccess();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 50000,
            'method' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'Payments can only be created while the invoice is unpaid.');

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    /**
     * Verify an invalid payment method is rejected by validation.
     */
    public function test_invalid_method_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 50000,
            'method' => 'bank_transfer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('method');
    }

    /**
     * Verify a non-positive amount is rejected by validation.
     */
    public function test_non_positive_amount_is_rejected(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 0,
            'method' => 'paypal',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    /**
     * Verify creating a payment for a missing invoice returns 404.
     */
    public function test_payment_for_missing_invoice_returns_not_found(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices/999999/payments', [
            'amount' => 50000,
            'method' => 'paypal',
        ])->assertNotFound();
    }

    /**
     * Verify roles without PAYMENTS.CREATE are forbidden from creating payments.
     */
    public function test_roles_without_permission_cannot_create_payments(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson("/api/invoices/{$invoice->id}/payments", [
                'amount' => 50000,
                'method' => 'paypal',
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PAYMENTS.CREATE');
        }
    }

    /**
     * Verify unauthenticated payment requests are rejected.
     */
    public function test_unauthenticated_payment_request_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 50000,
            'method' => 'paypal',
        ])->assertUnauthorized();
    }

    /**
     * Verify a PayPal failure bubbles up as a server error and rolls back the payment insert.
     */
    public function test_paypal_failure_returns_server_error_and_creates_no_payment(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([], 500),
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 50000,
            'method' => 'paypal',
        ])->assertStatus(500);

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * Fake a successful PayPal OAuth2 token exchange and Order capture.
     */
    private function fakePayPalCapture(string $orderId, string $status = 'COMPLETED', string $captureId = 'CAPTURE-1'): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_ACCESS_TOKEN',
            ], 200),
            "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'status' => $status,
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [
                            ['id' => $captureId, 'status' => $status],
                        ],
                    ],
                ]],
            ], 201),
        ]);
    }

    /**
     * Verify capturing a payment that fully settles the invoice marks it paid.
     */
    public function test_cashier_can_capture_payment_that_fully_settles_invoice(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-123',
        ]);
        $this->fakePayPalCapture('ORDER-123', 'COMPLETED', 'CAPTURE-1');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Payment captured',
                'data' => [
                    'status' => 'completed',
                    'provider_capture_id' => 'CAPTURE-1',
                ],
            ]);

        // PayPal's capture endpoint requires the body to be a JSON object; an empty PHP array
        // would encode to `[]` and PayPal rejects it as MALFORMED_REQUEST_JSON.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/capture') && $request->body() === '{}');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_COMPLETED,
            'provider_capture_id' => 'CAPTURE-1',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::STATUS_PAID,
        ]);
    }

    /**
     * Verify capture reconciles the payment method to what PayPal reports the buyer
     * actually paid with, since the buyer can switch funding sources on PayPal's page.
     */
    public function test_capture_updates_method_to_match_the_actual_paypal_funding_source(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'method' => Payment::METHOD_PAYPAL,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-123',
        ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_ACCESS_TOKEN',
            ], 200),
            'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-123/capture' => Http::response([
                'status' => 'COMPLETED',
                'payment_source' => [
                    'card' => ['brand' => 'VISA', 'last_digits' => '4496'],
                ],
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [
                            ['id' => 'CAPTURE-1', 'status' => 'COMPLETED'],
                        ],
                    ],
                ]],
            ], 201),
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")
            ->assertOk()
            ->assertJson([
                'data' => ['method' => 'visa'],
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'method' => Payment::METHOD_VISA,
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }

    /**
     * Verify capturing a partial payment completes the payment but leaves the invoice unpaid.
     */
    public function test_capturing_partial_payment_leaves_invoice_unpaid(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 80000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-456',
        ]);
        $this->fakePayPalCapture('ORDER-456');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_COMPLETED]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_UNPAID]);
    }

    /**
     * Verify a PayPal capture that does not complete marks the payment failed without touching
     * the invoice.
     */
    public function test_capture_marks_payment_failed_when_paypal_does_not_complete_it(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-789',
        ]);
        $this->fakePayPalCapture('ORDER-789', 'VOIDED');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")
            ->assertOk()
            ->assertJson([
                'message' => 'Payment capture failed',
                'data' => ['status' => 'failed'],
            ]);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_FAILED]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_UNPAID]);
    }

    /**
     * Verify capturing a second payment that would push completed total past the invoice total
     * is rejected before ever calling PayPal.
     */
    public function test_capture_rejected_when_it_would_exceed_invoice_total(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        Payment::factory()->completed()->create(['invoice_id' => $invoice->id, 'amount' => 150000]);
        $secondPayment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-999',
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$secondPayment->id}/capture")
            ->assertStatus(422)
            ->assertJsonPath('errors.payment.0', 'Capturing this payment would exceed the invoice total.');

        $this->assertDatabaseHas('payments', ['id' => $secondPayment->id, 'status' => Payment::STATUS_PENDING]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_UNPAID]);
        Http::assertNothingSent();
    }

    /**
     * Verify re-capturing a settled payment reports the existing result instead of
     * failing: PayPal may redirect the customer back more than once, and the return
     * page is replayed by the browser back button.
     */
    public function test_capture_is_idempotent_for_an_already_completed_payment(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_PAID]);
        $payment = Payment::factory()->completed()->create(['invoice_id' => $invoice->id, 'amount' => 100000]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Payment captured')
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.status', Payment::STATUS_COMPLETED);

        // The money was already taken; a replay must not reach PayPal again.
        Http::assertNothingSent();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }

    /**
     * Verify a payment that is neither pending nor completed cannot be captured.
     */
    public function test_capture_rejected_when_payment_is_not_pending(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_FAILED,
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")
            ->assertStatus(422)
            ->assertJsonPath('errors.payment.0', 'Only pending payments can be captured.');

        Http::assertNothingSent();
    }

    /**
     * Verify capturing a missing payment returns 404.
     */
    public function test_capture_for_missing_payment_returns_not_found(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/payments/999999/capture')->assertNotFound();
    }

    /**
     * Verify roles without PAYMENTS.CAPTURE are forbidden from capturing payments.
     */
    public function test_roles_without_permission_cannot_capture_payments(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id, 'status' => Payment::STATUS_PENDING]);

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson("/api/payments/{$payment->id}/capture")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PAYMENTS.CAPTURE');
        }
    }

    /**
     * Verify unauthenticated capture requests are rejected.
     */
    public function test_unauthenticated_capture_request_is_rejected(): void
    {
        $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

        $this->postJson("/api/payments/{$payment->id}/capture")->assertUnauthorized();
    }

    /**
     * Verify a PayPal infrastructure failure during capture bubbles up as a server error and
     * leaves the payment untouched.
     */
    public function test_paypal_failure_during_capture_returns_server_error_and_leaves_payment_pending(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-500',
        ]);

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([], 500),
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/capture")->assertStatus(500);

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_PENDING]);
    }

    /**
     * Verify a cashier can cancel a pending payment (e.g. buyer backed out of PayPal checkout).
     */
    public function test_cashier_can_cancel_a_pending_payment(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_PENDING,
            'provider_order_id' => 'ORDER-CANCEL-1',
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/cancel")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Payment cancelled',
                'data' => ['status' => 'cancelled'],
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_CANCELLED,
        ]);
    }

    /**
     * Verify re-cancelling an already cancelled payment reports the existing result instead of
     * failing: PayPal may redirect the customer back to the cancel page more than once.
     */
    public function test_cancel_is_idempotent_for_an_already_cancelled_payment(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Payment::STATUS_CANCELLED);
    }

    /**
     * Verify a payment that is neither pending nor cancelled cannot be cancelled.
     */
    public function test_cancel_rejected_when_payment_is_not_pending(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->completed()->create(['invoice_id' => $invoice->id]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson("/api/payments/{$payment->id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('errors.payment.0', 'Only pending payments can be cancelled.');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => Payment::STATUS_COMPLETED]);
    }

    /**
     * Verify cancelling a missing payment returns 404.
     */
    public function test_cancel_for_missing_payment_returns_not_found(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/payments/999999/cancel')->assertNotFound();
    }

    /**
     * Verify roles without PAYMENTS.CANCEL are forbidden from cancelling payments.
     */
    public function test_roles_without_permission_cannot_cancel_payments(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create(['invoice_id' => $invoice->id, 'status' => Payment::STATUS_PENDING]);

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson("/api/payments/{$payment->id}/cancel")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PAYMENTS.CANCEL');
        }
    }

    /**
     * Verify unauthenticated cancel requests are rejected.
     */
    public function test_unauthenticated_cancel_request_is_rejected(): void
    {
        $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

        $this->postJson("/api/payments/{$payment->id}/cancel")->assertUnauthorized();
    }

    /**
     * Verify method=visa uses the identical PayPal Order/Capture flow as method=paypal — the
     * backend does not branch on payment method, only records it.
     */
    public function test_visa_method_uses_the_same_paypal_order_and_capture_flow(): void
    {
        $invoice = Invoice::factory()->create(['total' => 100000, 'status' => Invoice::STATUS_UNPAID]);
        $this->fakePayPalSuccess('ORDER-VISA-1');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $paymentId = $this->postJson("/api/invoices/{$invoice->id}/payments", [
            'amount' => 100000,
            'method' => 'visa',
        ])->assertCreated()
            ->assertJsonPath('data.method', 'visa')
            ->assertJsonPath('data.provider', 'paypal')
            ->json('data.id');

        $this->fakePayPalCapture('ORDER-VISA-1');

        $this->postJson("/api/payments/{$paymentId}/capture")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.method', 'visa');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'method' => 'visa',
            'status' => Payment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_PAID]);
    }

    /**
     * Verify payments can be filtered by invoice and by PayPal order id.
     */
    public function test_index_filters_by_invoice_and_provider_order_id(): void
    {
        $invoice = Invoice::factory()->create(['total' => 200000, 'status' => Invoice::STATUS_UNPAID]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'provider_order_id' => 'ORDER-INDEX-1',
        ]);
        Payment::factory()->create(['provider_order_id' => 'ORDER-INDEX-2']);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson("/api/payments?invoice_id={$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/payments?provider_order_id=ORDER-INDEX-1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Verify roles without the list permission cannot browse payments.
     */
    public function test_roles_without_permission_cannot_list_payments(): void
    {
        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->getJson('/api/payments')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PAYMENTS.FINDALL');
    }

    /**
     * Verify unauthenticated requests to the list endpoint are rejected.
     */
    public function test_unauthenticated_list_request_is_rejected(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();
    }

    /**
     * Verify a cashier can retrieve a PayPal client token for Card Fields init.
     */
    public function test_cashier_can_retrieve_paypal_client_token(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_JWT_CLIENT_TOKEN',
            ], 200),
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson('/api/payments/paypal/client-token')
            ->assertOk()
            ->assertJsonPath('data.client_token', 'FAKE_JWT_CLIENT_TOKEN');

        // The Web SDK v6 createInstance() requires a JWT clientToken, which only the
        // response_type=client_token grant returns (the plain client_credentials
        // grant used elsewhere returns a REST API Bearer token, not a JWT). Guard
        // against silently regressing to the plain grant.
        Http::assertSent(
            fn ($request): bool => str_contains($request->url(), '/v1/oauth2/token')
                && str_contains($request->body(), 'response_type=client_token'),
        );
    }

    /**
     * Verify roles without payment-create permission cannot retrieve a client token.
     */
    public function test_roles_without_permission_cannot_retrieve_client_token(): void
    {
        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->getJson('/api/payments/paypal/client-token')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PAYMENTS.CREATE');
        }
    }

    /**
     * Verify unauthenticated requests to the client token endpoint are rejected.
     */
    public function test_unauthenticated_client_token_request_is_rejected(): void
    {
        $this->getJson('/api/payments/paypal/client-token')->assertUnauthorized();
    }

    /**
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
