<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Examination;
use App\Models\Medicine;
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
 * Verify prescription creation rules, stock deduction, and RBAC access.
 */
class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by prescription management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify doctors can create an empty prescription whose doctor is assigned from the examination.
     */
    public function test_doctor_can_create_prescription_without_items(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'notes' => 'Tái khám sau 5 ngày',
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Prescription created',
                'data' => [
                    'examination_id' => $examination->id,
                    'doctor_id' => $examination->doctor_id,
                    'notes' => 'Tái khám sau 5 ngày',
                    'items' => [],
                ],
            ]);

        $this->assertDatabaseHas('prescriptions', [
            'examination_id' => $examination->id,
            'doctor_id' => $examination->doctor_id,
            'notes' => 'Tái khám sau 5 ngày',
        ]);
    }

    /**
     * Verify creating a prescription with items deducts stock and creates the item rows.
     */
    public function test_doctor_can_create_prescription_with_items_and_deducts_stock(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 3,
                    'dosage' => '2 viên/lần, ngày 2 lần',
                    'usage_instruction' => 'Uống sau ăn',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.items.0.medicine_id', $medicine->id)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.medicine.code', $medicine->code);

        $this->assertSame(7, $medicine->refresh()->stock);
        $this->assertDatabaseHas('prescription_items', [
            'medicine_id' => $medicine->id,
            'quantity' => 3,
            'dosage' => '2 viên/lần, ngày 2 lần',
        ]);
    }

    /**
     * Verify the server-owned doctor field cannot be supplied by clients.
     */
    public function test_store_rejects_client_supplied_doctor_id(): void
    {
        $examination = Examination::factory()->create();
        $otherDoctor = Doctor::factory()->create();

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'doctor_id' => $otherDoctor->id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.doctor_id.0',
                'The prescription doctor is assigned from the examination.',
            );

        $this->assertDatabaseMissing('prescriptions', [
            'examination_id' => $examination->id,
        ]);
    }

    /**
     * Verify a nonexistent examination is rejected by validation.
     */
    public function test_store_validates_examination_exists(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/prescriptions', [
            'examination_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.examination_id.0',
                'The selected examination does not exist.',
            );
    }

    /**
     * Verify an examination cannot receive a second prescription.
     */
    public function test_store_rejects_duplicate_prescription_for_examination(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($examination->doctor->user);

        $payload = ['examination_id' => $examination->id];

        $this->postJson('/api/prescriptions', $payload)->assertCreated();

        $this->postJson('/api/prescriptions', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.examination_id.0',
                'The examination already has a prescription.',
            );

        $this->assertSame(
            1,
            Prescription::query()->where('examination_id', $examination->id)->count(),
        );
    }

    /**
     * Verify duplicate medicines within the same request are rejected before touching stock.
     */
    public function test_store_rejects_duplicate_medicines_in_items(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 1, 'dosage' => 'A'],
                ['medicine_id' => $medicine->id, 'quantity' => 2, 'dosage' => 'B'],
            ],
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['items.0.medicine_id', 'items.1.medicine_id']]);

        $this->assertSame(10, $medicine->refresh()->stock);
        $this->assertDatabaseMissing('prescriptions', ['examination_id' => $examination->id]);
    }

    /**
     * Verify item quantity and medicine existence are validated.
     */
    public function test_store_validates_item_fields(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                ['medicine_id' => 999999, 'quantity' => 0],
            ],
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['items.0.medicine_id', 'items.0.quantity', 'items.0.dosage'],
            ]);
    }

    /**
     * Verify prescribing an inactive medicine rolls back the whole transaction.
     */
    public function test_store_rejects_inactive_medicine_and_rolls_back(): void
    {
        $examination = Examination::factory()->create();
        $activeMedicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);
        $inactiveMedicine = Medicine::factory()->create(['stock' => 10, 'is_active' => false]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                [
                    'medicine_id' => $activeMedicine->id,
                    'quantity' => 2,
                    'dosage' => 'A',
                ],
                [
                    'medicine_id' => $inactiveMedicine->id,
                    'quantity' => 1,
                    'dosage' => 'B',
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.items.0',
                "Medicine {$inactiveMedicine->code} is not active.",
            );

        $this->assertSame(10, $activeMedicine->refresh()->stock);
        $this->assertSame(10, $inactiveMedicine->refresh()->stock);
        $this->assertDatabaseMissing('prescriptions', ['examination_id' => $examination->id]);
    }

    /**
     * Verify prescribing more than the available stock rolls back the whole transaction.
     */
    public function test_store_rejects_insufficient_stock_and_rolls_back(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 2, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                [
                    'medicine_id' => $medicine->id,
                    'quantity' => 5,
                    'dosage' => 'A',
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.items.0',
                "Medicine {$medicine->code} has insufficient stock.",
            );

        $this->assertSame(2, $medicine->refresh()->stock);
        $this->assertDatabaseMissing('prescriptions', ['examination_id' => $examination->id]);
    }

    /**
     * Verify roles without PRESCRIPTIONS.CREATE are forbidden from creating prescriptions.
     */
    public function test_roles_without_permission_cannot_create_prescriptions(): void
    {
        $examination = Examination::factory()->create();

        foreach (['CASHIER', 'PHARMACIST', 'RECEPTIONIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson('/api/prescriptions', [
                'examination_id' => $examination->id,
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.CREATE');
        }
    }

    /**
     * Verify unauthenticated requests are rejected.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/prescriptions', [])->assertUnauthorized();
    }

    /**
     * Verify the response eager loads items, their medicine, and the doctor context.
     */
    public function test_response_includes_items_medicine_and_doctor_context(): void
    {
        $examination = Examination::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);

        Sanctum::actingAs($examination->doctor->user);

        $this->postJson('/api/prescriptions', [
            'examination_id' => $examination->id,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 1, 'dosage' => 'A'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.doctor.id', $examination->doctor_id)
            ->assertJsonPath('data.doctor.user.id', $examination->doctor->user_id)
            ->assertJsonPath('data.items.0.medicine.name', $medicine->name);
    }

    /**
     * Verify a doctor can add a new medicine line to an existing prescription.
     */
    public function test_doctor_can_add_item_to_prescription(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->postJson("/api/prescriptions/{$prescription->id}/items", [
            'medicine_id' => $medicine->id,
            'quantity' => 4,
            'dosage' => '1 viên/lần, ngày 2 lần',
            'usage_instruction' => 'Uống trước ăn',
        ])->assertCreated()
            ->assertJsonPath('message', 'Prescription item added')
            ->assertJsonPath('data.medicine_id', $medicine->id)
            ->assertJsonPath('data.quantity', 4);

        $this->assertSame(6, $medicine->refresh()->stock);
        $this->assertDatabaseHas('prescription_items', [
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ]);
    }

    /**
     * Verify adding a medicine already on the prescription is rejected instead of duplicated.
     */
    public function test_add_item_rejects_duplicate_medicine(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);
        $existingItem = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 2,
        ]);
        $medicine->update(['stock' => 8]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->postJson("/api/prescriptions/{$prescription->id}/items", [
            'medicine_id' => $medicine->id,
            'quantity' => 1,
            'dosage' => 'A',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.medicine_id.0',
                "Medicine {$medicine->code} is already in this prescription. Use updateItem to change the quantity.",
            );

        $this->assertSame(8, $medicine->refresh()->stock);
        $this->assertSame(
            1,
            PrescriptionItem::query()
                ->where('prescription_id', $prescription->id)
                ->where('medicine_id', $medicine->id)
                ->count(),
        );
        $this->assertSame(2, $existingItem->refresh()->quantity);
    }

    /**
     * Verify adding an inactive medicine is rejected.
     */
    public function test_add_item_rejects_inactive_medicine(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => false]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->postJson("/api/prescriptions/{$prescription->id}/items", [
            'medicine_id' => $medicine->id,
            'quantity' => 1,
            'dosage' => 'A',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.items.0', "Medicine {$medicine->code} is not active.");

        $this->assertSame(10, $medicine->refresh()->stock);
    }

    /**
     * Verify adding more than the available stock is rejected.
     */
    public function test_add_item_rejects_insufficient_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 2, 'is_active' => true]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->postJson("/api/prescriptions/{$prescription->id}/items", [
            'medicine_id' => $medicine->id,
            'quantity' => 5,
            'dosage' => 'A',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.items.0', "Medicine {$medicine->code} has insufficient stock.");

        $this->assertSame(2, $medicine->refresh()->stock);
    }

    /**
     * Verify adding an item to a nonexistent prescription returns 404.
     */
    public function test_add_item_to_missing_prescription_returns_404(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/prescriptions/999999/items', [
            'medicine_id' => Medicine::factory()->create()->id,
            'quantity' => 1,
            'dosage' => 'A',
        ])->assertNotFound();
    }

    /**
     * Verify increasing an item's quantity deducts only the stock delta.
     */
    public function test_doctor_can_increase_item_quantity_within_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'quantity' => 5,
        ])->assertOk()
            ->assertJsonPath('message', 'Prescription item updated')
            ->assertJsonPath('data.quantity', 5);

        $this->assertSame(7, $medicine->refresh()->stock);
        $this->assertSame(5, $item->refresh()->quantity);
    }

    /**
     * Verify increasing an item's quantity beyond available stock is rejected.
     */
    public function test_update_item_rejects_increase_beyond_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 3, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', "Medicine {$medicine->code} has insufficient stock.");

        $this->assertSame(3, $medicine->refresh()->stock);
        $this->assertSame(2, $item->refresh()->quantity);
    }

    /**
     * Verify decreasing an item's quantity returns the difference to stock.
     */
    public function test_doctor_can_decrease_item_quantity_and_returns_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 5, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('data.quantity', 1);

        $this->assertSame(8, $medicine->refresh()->stock);
        $this->assertSame(1, $item->refresh()->quantity);
    }

    /**
     * Verify decreasing quantity on an inactive medicine is still allowed (returning stock is safe).
     */
    public function test_update_item_allows_decrease_for_inactive_medicine(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 1, 'is_active' => false]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 4,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'quantity' => 1,
        ])->assertOk();

        $this->assertSame(4, $medicine->refresh()->stock);
    }

    /**
     * Verify updating only dosage or usage instruction leaves stock untouched.
     */
    public function test_update_item_can_change_dosage_without_touching_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 5, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'dosage' => 'Liều mới',
            'usage_instruction' => null,
        ])->assertOk()
            ->assertJsonPath('data.dosage', 'Liều mới')
            ->assertJsonPath('data.usage_instruction', null);

        $this->assertSame(5, $medicine->refresh()->stock);
        $this->assertSame(2, $item->refresh()->quantity);
    }

    /**
     * Verify the item medicine cannot be changed through updateItem.
     */
    public function test_update_item_rejects_medicine_id(): void
    {
        $prescription = Prescription::factory()->create();
        $item = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);
        $otherMedicine = Medicine::factory()->create();

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'medicine_id' => $otherMedicine->id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.medicine_id.0',
                'The prescription item medicine cannot be changed.',
            );

        $this->assertSame($item->medicine_id, $item->refresh()->medicine_id);
    }

    /**
     * Verify an empty update payload is rejected.
     */
    public function test_update_item_rejects_empty_payload(): void
    {
        $prescription = Prescription::factory()->create();
        $item = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.item.0',
                'At least one prescription item field must be provided.',
            );
    }

    /**
     * Verify an item that belongs to a different prescription cannot be updated or removed.
     */
    public function test_update_and_remove_item_reject_mismatched_prescription(): void
    {
        $prescription = Prescription::factory()->create();
        $otherPrescription = Prescription::factory()->create();
        $item = PrescriptionItem::factory()->create(['prescription_id' => $otherPrescription->id]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
            'quantity' => 1,
        ])->assertNotFound();

        $this->deleteJson("/api/prescriptions/{$prescription->id}/items/{$item->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('prescription_items', ['id' => $item->id]);
    }

    /**
     * Verify a doctor can remove a prescription item and its quantity returns to stock.
     */
    public function test_doctor_can_remove_item_and_returns_stock(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 5, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'medicine_id' => $medicine->id,
            'quantity' => 3,
        ]);

        Sanctum::actingAs($prescription->doctor->user);

        $this->deleteJson("/api/prescriptions/{$prescription->id}/items/{$item->id}")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Prescription item removed',
                'data' => null,
            ]);

        $this->assertSame(8, $medicine->refresh()->stock);
        $this->assertDatabaseMissing('prescription_items', ['id' => $item->id]);
    }

    /**
     * Verify removing a nonexistent item returns 404.
     */
    public function test_remove_missing_item_returns_404(): void
    {
        $prescription = Prescription::factory()->create();

        Sanctum::actingAs($prescription->doctor->user);

        $this->deleteJson("/api/prescriptions/{$prescription->id}/items/999999")
            ->assertNotFound();
    }

    /**
     * Verify each item-management action is protected by its mapped permission.
     */
    public function test_roles_without_permission_cannot_manage_items(): void
    {
        $prescription = Prescription::factory()->create();
        $medicine = Medicine::factory()->create(['stock' => 10, 'is_active' => true]);
        $item = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);

        foreach (['CASHIER', 'RECEPTIONIST'] as $role) {
            Sanctum::actingAs($this->createUser($role));

            $this->postJson("/api/prescriptions/{$prescription->id}/items", [
                'medicine_id' => $medicine->id,
                'quantity' => 1,
                'dosage' => 'A',
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.ADDITEM');

            $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [
                'quantity' => 1,
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.UPDATEITEM');

            $this->deleteJson("/api/prescriptions/{$prescription->id}/items/{$item->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.REMOVEITEM');
        }
    }

    /**
     * Verify unauthenticated requests to item-management endpoints are rejected.
     */
    public function test_unauthenticated_requests_to_item_endpoints_are_rejected(): void
    {
        $prescription = Prescription::factory()->create();
        $item = PrescriptionItem::factory()->create(['prescription_id' => $prescription->id]);

        $this->postJson("/api/prescriptions/{$prescription->id}/items", [])->assertUnauthorized();
        $this->patchJson("/api/prescriptions/{$prescription->id}/items/{$item->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/prescriptions/{$prescription->id}/items/{$item->id}")->assertUnauthorized();
    }

    /**
     * Verify prescriptions can be filtered by patient and doctor and are paginated.
     */
    public function test_index_filters_by_patient_and_doctor(): void
    {
        $prescription = Prescription::factory()->create();

        Sanctum::actingAs($prescription->doctor->user);

        $this->getJson("/api/prescriptions?doctor_id={$prescription->doctor_id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $prescription->id);

        $patientId = $prescription->examination->patient_id;

        $this->getJson("/api/prescriptions?patient_id={$patientId}")
            ->assertOk()
            ->assertJsonPath('data.0.examination.patient.id', $patientId);
    }

    /**
     * Verify a prescription's detail response includes items, medicine, doctor, and examination context.
     */
    public function test_show_returns_prescription_with_context(): void
    {
        $prescription = Prescription::factory()->create();

        Sanctum::actingAs($prescription->doctor->user);

        $this->getJson("/api/prescriptions/{$prescription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $prescription->id)
            ->assertJsonPath('data.doctor.id', $prescription->doctor_id)
            ->assertJsonPath('data.examination.id', $prescription->examination_id);
    }

    /**
     * Verify roles without the list/detail permission cannot browse prescriptions.
     */
    public function test_roles_without_permission_cannot_list_or_view_prescriptions(): void
    {
        $prescription = Prescription::factory()->create();

        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->getJson('/api/prescriptions')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.FINDALL');

        $this->getJson("/api/prescriptions/{$prescription->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PRESCRIPTIONS.FINDONE');
    }

    /**
     * Verify unauthenticated requests to list/detail endpoints are rejected.
     */
    public function test_unauthenticated_requests_to_list_and_show_are_rejected(): void
    {
        $prescription = Prescription::factory()->create();

        $this->getJson('/api/prescriptions')->assertUnauthorized();
        $this->getJson("/api/prescriptions/{$prescription->id}")->assertUnauthorized();
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
