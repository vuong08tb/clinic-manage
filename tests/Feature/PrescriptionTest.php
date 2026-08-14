<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Examination;
use App\Models\Medicine;
use App\Models\Prescription;
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
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
