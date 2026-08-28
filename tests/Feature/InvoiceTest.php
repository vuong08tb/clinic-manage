<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify invoice creation billing rules, listing filters, and RBAC access.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by invoice management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify a cashier can bill an examination with prescription items, summing medicine cost
     * with the configured examination fee and subtracting the discount.
     */
    public function test_cashier_can_create_invoice_from_examination_with_prescription_items(): void
    {
        $examination = Examination::factory()->create();
        $prescription = Prescription::factory()->create(['examination_id' => $examination->id]);
        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => Medicine::factory()->create(['price' => 15000]),
            'quantity' => 3,
        ]);
        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => Medicine::factory()->create(['price' => 5000]),
            'quantity' => 2,
        ]);

        $examinationFee = (float) config('clinic.examination_fee');
        $expectedSubtotal = 45000 + 10000 + $examinationFee;
        $expectedTotal = $expectedSubtotal - 20000;

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
            'discount' => 20000,
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Invoice created',
                'data' => [
                    'examination_id' => $examination->id,
                    'subtotal' => number_format($expectedSubtotal, 2, '.', ''),
                    'discount' => '20000.00',
                    'total' => number_format($expectedTotal, 2, '.', ''),
                    'status' => 'unpaid',
                ],
            ])
            ->assertJsonPath('data.invoice_code', fn (string $code): bool => (bool) preg_match('/^INV-\d{6}$/', $code));

        $this->assertDatabaseHas('invoices', [
            'examination_id' => $examination->id,
            'status' => Invoice::STATUS_UNPAID,
        ]);
    }

    /**
     * Verify an examination without a prescription is billed for the examination fee only.
     */
    public function test_cashier_can_create_invoice_from_examination_without_prescription(): void
    {
        $examination = Examination::factory()->create();
        $examinationFee = (float) config('clinic.examination_fee');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
        ])->assertCreated()
            ->assertJson([
                'data' => [
                    'subtotal' => number_format($examinationFee, 2, '.', ''),
                    'discount' => '0.00',
                    'total' => number_format($examinationFee, 2, '.', ''),
                ],
            ]);
    }

    /**
     * Verify creating a second invoice for an already-invoiced examination is rejected.
     */
    public function test_store_rejects_duplicate_invoice_for_examination(): void
    {
        $examination = Examination::factory()->create();
        Invoice::factory()->create(['examination_id' => $examination->id]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.examination.0', 'The examination already has an invoice.');

        $this->assertSame(1, Invoice::query()->where('examination_id', $examination->id)->count());
    }

    /**
     * Verify a nonexistent examination is rejected by validation.
     */
    public function test_store_validates_examination_exists(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.examination_id.0', 'The selected examination does not exist.');
    }

    /**
     * Verify a discount larger than the computed subtotal is rejected without creating an invoice.
     */
    public function test_store_rejects_discount_exceeding_subtotal(): void
    {
        $examination = Examination::factory()->create();
        $examinationFee = (float) config('clinic.examination_fee');

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
            'discount' => $examinationFee + 1,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.discount.0', 'Discount cannot exceed the invoice subtotal.');

        $this->assertDatabaseMissing('invoices', ['examination_id' => $examination->id]);
    }

    /**
     * Verify a negative discount is rejected by validation.
     */
    public function test_store_rejects_negative_discount(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
            'discount' => -1,
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['discount']]);
    }

    /**
     * Verify invoice codes are generated uniquely across multiple invoices.
     */
    public function test_invoice_code_is_generated_uniquely(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $first = $this->postJson('/api/invoices', [
            'examination_id' => Examination::factory()->create()->id,
        ])->assertCreated()->json('data.invoice_code');

        $second = $this->postJson('/api/invoices', [
            'examination_id' => Examination::factory()->create()->id,
        ])->assertCreated()->json('data.invoice_code');

        $this->assertNotSame($first, $second);
    }

    /**
     * Verify the store response eager loads the examination context and prescription items.
     */
    public function test_store_response_includes_examination_and_prescription_items(): void
    {
        $examination = Examination::factory()->create();
        $prescription = Prescription::factory()->create(['examination_id' => $examination->id]);
        $medicine = Medicine::factory()->create();
        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->postJson('/api/invoices', [
            'examination_id' => $examination->id,
        ])->assertCreated()
            ->assertJsonPath('data.examination.id', $examination->id)
            ->assertJsonPath('data.examination.patient.id', $examination->patient_id)
            ->assertJsonPath('data.examination.doctor.id', $examination->doctor_id)
            ->assertJsonPath('data.items.0.medicine_id', $medicine->id)
            // Cashiers hold no MEDICINES permission, so the billing view exposes
            // every medicine field except its stock level.
            ->assertJsonPath('data.items.0.medicine.name', $medicine->name)
            ->assertJsonMissingPath('data.items.0.medicine.stock');
    }

    /**
     * Verify roles without INVOICES.CREATE are forbidden from creating invoices.
     */
    public function test_roles_without_permission_cannot_create_invoices(): void
    {
        $examination = Examination::factory()->create();

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson('/api/invoices', [
                'examination_id' => $examination->id,
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: INVOICES.CREATE');
        }
    }

    /**
     * Verify unauthenticated store requests are rejected.
     */
    public function test_unauthenticated_store_request_is_rejected(): void
    {
        $this->postJson('/api/invoices', [])->assertUnauthorized();
    }

    /**
     * Verify invoice lists combine status/examination filters, paginate, and order by issued_at.
     */
    public function test_index_filters_and_paginates_invoices(): void
    {
        $examination = Examination::factory()->create();
        $otherExamination = Examination::factory()->create();

        $older = Invoice::factory()->create([
            'examination_id' => $examination->id,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => '2026-08-10 09:00:00',
        ]);
        $newer = Invoice::factory()->create([
            'examination_id' => Examination::factory()->create()->id,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => '2026-08-11 09:00:00',
        ]);
        Invoice::factory()->create([
            'examination_id' => $otherExamination->id,
            'status' => Invoice::STATUS_PAID,
            'issued_at' => '2026-08-12 09:00:00',
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson('/api/invoices?status=unpaid&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Invoices retrieved')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 2,
                    'last_page' => 2,
                ],
            ]);

        $this->getJson('/api/invoices?status=unpaid&per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id);

        $this->getJson("/api/invoices?examination_id={$otherExamination->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.examination_id', $otherExamination->id);
    }

    /**
     * Verify the index response does not eager load prescription items, so the "items" key is
     * omitted from each invoice.
     */
    public function test_index_does_not_include_prescription_items(): void
    {
        $examination = Examination::factory()->create();
        $prescription = Prescription::factory()->create(['examination_id' => $examination->id]);
        PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);
        Invoice::factory()->create(['examination_id' => $examination->id]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonMissingPath('data.0.items');
    }

    /**
     * Verify invalid invoice list filters return validation errors.
     */
    public function test_index_rejects_invalid_filters(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson('/api/invoices?status=refunded&examination_id=999999&page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['status', 'examination_id', 'page', 'per_page'],
            ]);
    }

    /**
     * Verify roles without INVOICES.FINDALL are forbidden from listing invoices.
     */
    public function test_roles_without_permission_cannot_list_invoices(): void
    {
        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->getJson('/api/invoices')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: INVOICES.FINDALL');
        }
    }

    /**
     * Verify unauthenticated index requests are rejected.
     */
    public function test_unauthenticated_index_request_is_rejected(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }

    /**
     * Verify the show response includes the examination context and prescription items.
     */
    public function test_cashier_can_view_invoice_with_examination_and_items(): void
    {
        $examination = Examination::factory()->create();
        $prescription = Prescription::factory()->create(['examination_id' => $examination->id]);
        $medicine = Medicine::factory()->create();
        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 1,
        ]);
        $invoice = Invoice::factory()->create(['examination_id' => $examination->id]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Invoice retrieved')
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.examination.id', $examination->id)
            ->assertJsonPath('data.examination.patient.id', $examination->patient_id)
            ->assertJsonPath('data.examination.doctor.id', $examination->doctor_id)
            ->assertJsonPath('data.items.0.medicine_id', $medicine->id)
            // Cashiers hold no MEDICINES permission, so the billing view exposes
            // every medicine field except its stock level.
            ->assertJsonPath('data.items.0.medicine.name', $medicine->name)
            ->assertJsonMissingPath('data.items.0.medicine.stock');
    }

    /**
     * Verify a nonexistent invoice returns 404.
     */
    public function test_show_missing_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson('/api/invoices/999999')->assertNotFound();
    }

    /**
     * Verify roles without INVOICES.FINDONE are forbidden from viewing an invoice.
     */
    public function test_roles_without_permission_cannot_view_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->getJson("/api/invoices/{$invoice->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: INVOICES.FINDONE');
        }
    }

    /**
     * Verify unauthenticated show requests are rejected.
     */
    public function test_unauthenticated_show_request_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->getJson("/api/invoices/{$invoice->id}")->assertUnauthorized();
    }

    /**
     * Verify a cashier can update the discount of an unpaid invoice and the total is recomputed.
     */
    public function test_cashier_can_update_discount_of_unpaid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 250000,
            'discount' => 0,
            'total' => 250000,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 50000])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invoice updated',
                'data' => [
                    'subtotal' => '250000.00',
                    'discount' => '50000.00',
                    'total' => '200000.00',
                ],
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'discount' => 50000,
            'total' => 200000,
        ]);
    }

    /**
     * Verify a discount larger than the invoice subtotal is rejected without changing the invoice.
     */
    public function test_update_rejects_discount_exceeding_subtotal(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 100000,
            'discount' => 0,
            'total' => 100000,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 100001])
            ->assertUnprocessable()
            ->assertJsonPath('errors.discount.0', 'Discount cannot exceed the invoice subtotal.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'discount' => 0, 'total' => 100000]);
    }

    /**
     * Verify a negative or missing discount is rejected by validation.
     */
    public function test_update_rejects_negative_discount(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => -1])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['discount']]);
    }

    /**
     * Verify a prohibited field in the update payload is rejected.
     */
    public function test_update_rejects_prohibited_fields(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'discount' => 10000,
            'status' => Invoice::STATUS_PAID,
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);
    }

    /**
     * Verify a non-unpaid invoice cannot have its discount updated.
     */
    public function test_update_rejects_non_unpaid_invoice(): void
    {
        foreach ([Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED] as $status) {
            $invoice = Invoice::factory()->create(['status' => $status]);

            Sanctum::actingAs($this->createUser('CASHIER'));

            $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 1000])
                ->assertUnprocessable()
                ->assertJsonPath('errors.invoice.0', 'Invoice can only be modified while unpaid.');
        }
    }

    /**
     * Verify an invoice with a completed payment cannot be updated even while still unpaid
     * (partial payment).
     */
    public function test_update_rejects_invoice_with_completed_payment(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID, 'total' => 200000]);
        Payment::factory()->completed()->create(['invoice_id' => $invoice->id, 'amount' => 50000]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 1000])
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice.0', 'Invoice can only be modified while unpaid.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'discount' => 0]);
    }

    /**
     * Verify updating a nonexistent invoice returns 404.
     */
    public function test_update_missing_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson('/api/invoices/999999', ['discount' => 1000])->assertNotFound();
    }

    /**
     * Verify roles without INVOICES.UPDATE are forbidden from updating invoices.
     */
    public function test_roles_without_permission_cannot_update_invoices(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 1000])
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: INVOICES.UPDATE');
        }
    }

    /**
     * Verify unauthenticated update requests are rejected.
     */
    public function test_unauthenticated_update_request_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->patchJson("/api/invoices/{$invoice->id}", ['discount' => 1000])->assertUnauthorized();
    }

    /**
     * Verify a cashier can cancel an unpaid invoice.
     */
    public function test_cashier_can_cancel_unpaid_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invoice status updated',
                'data' => ['status' => 'cancelled'],
            ]);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_CANCELLED]);
    }

    /**
     * Verify a non-unpaid invoice cannot be cancelled again.
     */
    public function test_cancel_rejects_non_unpaid_invoice(): void
    {
        foreach ([Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED] as $status) {
            $invoice = Invoice::factory()->create(['status' => $status]);

            Sanctum::actingAs($this->createUser('CASHIER'));

            $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.invoice.0', 'Invoice can only be modified while unpaid.');
        }
    }

    /**
     * Verify an invoice with a completed payment cannot be cancelled even while still unpaid
     * (partial payment).
     */
    public function test_cancel_rejects_invoice_with_completed_payment(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID, 'total' => 200000]);
        Payment::factory()->completed()->create(['invoice_id' => $invoice->id, 'amount' => 50000]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice.0', 'Invoice can only be modified while unpaid.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_UNPAID]);
    }

    /**
     * Verify a status other than "cancelled" is rejected by validation.
     */
    public function test_update_status_rejects_status_other_than_cancelled(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Only cancelling an invoice is supported via this endpoint.');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_UNPAID]);
    }

    /**
     * Verify cancelling a nonexistent invoice returns 404.
     */
    public function test_update_status_missing_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->patchJson('/api/invoices/999999/status', ['status' => 'cancelled'])->assertNotFound();
    }

    /**
     * Verify roles without INVOICES.UPDATESTATUS are forbidden from cancelling invoices.
     */
    public function test_roles_without_permission_cannot_cancel_invoices(): void
    {
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_UNPAID]);

        foreach (['DOCTOR', 'RECEPTIONIST', 'PHARMACIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'cancelled'])
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: INVOICES.UPDATESTATUS');
        }
    }

    /**
     * Verify unauthenticated cancel requests are rejected.
     */
    public function test_unauthenticated_update_status_request_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->patchJson("/api/invoices/{$invoice->id}/status", ['status' => 'cancelled'])->assertUnauthorized();
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
