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
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
