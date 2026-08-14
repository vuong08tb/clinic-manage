<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify medicine CRUD, search, stock filter, validation, soft deletion, and RBAC access.
 */
class MedicineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by medicine management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify the administrator CRUD flow and soft deletion.
     */
    public function test_admin_can_complete_medicine_crud_and_soft_delete(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/medicines', $this->medicineData())
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Medicine created',
                'data' => [
                    'code' => 'MED-001',
                    'name' => 'Paracetamol 500mg',
                    'unit' => 'Vỉ',
                    'price' => '15000.00',
                    'stock' => 100,
                    'is_active' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'unit',
                    'price',
                    'stock',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $medicine = Medicine::query()->where('code', 'MED-001')->firstOrFail();

        $this->getJson("/api/medicines/{$medicine->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Medicine retrieved')
            ->assertJsonPath('data.code', 'MED-001');

        $this->patchJson("/api/medicines/{$medicine->id}", [
            'price' => 16000,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('message', 'Medicine updated')
            ->assertJsonPath('data.price', '16000.00')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/medicines/{$medicine->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Medicine deleted',
                'data' => null,
            ]);

        $this->assertSoftDeleted('medicines', ['id' => $medicine->id]);
        $this->assertNotNull(Medicine::withTrashed()->find($medicine->id));

        $this->getJson("/api/medicines/{$medicine->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * Verify a pharmacist can fully manage medicines like an administrator.
     */
    public function test_pharmacist_can_complete_medicine_crud(): void
    {
        Sanctum::actingAs($this->createUser('PHARMACIST'));

        $this->postJson('/api/medicines', $this->medicineData())
            ->assertCreated()
            ->assertJsonPath('data.code', 'MED-001');

        $medicine = Medicine::query()->firstOrFail();

        $this->patchJson("/api/medicines/{$medicine->id}", [
            'stock' => 50,
        ])->assertOk()
            ->assertJsonPath('data.stock', 50);

        $this->deleteJson("/api/medicines/{$medicine->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Medicine deleted');

        $this->assertSoftDeleted('medicines', ['id' => $medicine->id]);
    }

    /**
     * Verify stock adjustment applies a signed delta and rejects a negative result.
     */
    public function test_pharmacist_can_adjust_stock_up_and_down_but_not_below_zero(): void
    {
        Sanctum::actingAs($this->createUser('PHARMACIST'));

        $medicine = Medicine::factory()->create(['stock' => 50]);

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => 20,
            'note' => 'Nhập kho đợt mới',
        ])->assertOk()
            ->assertJsonPath('message', 'Medicine stock adjusted')
            ->assertJsonPath('data.stock', 70);

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => -30,
        ])->assertOk()
            ->assertJsonPath('data.stock', 40);

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => -1000,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.quantity.0', 'The resulting stock cannot be negative.');

        $this->assertSame(40, $medicine->refresh()->stock);
    }

    /**
     * Verify stock adjustment requires a numeric quantity.
     */
    public function test_adjust_stock_validation_requires_integer_quantity(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $medicine = Medicine::factory()->create(['stock' => 10]);

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['quantity']]);

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => 'not-a-number',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['quantity']]);
    }

    /**
     * Verify searching by name and code, and stock status filtering.
     */
    public function test_read_role_can_search_and_filter_by_stock_status(): void
    {
        $inStock = Medicine::factory()->create([
            'code' => 'MED-100001',
            'name' => 'Amoxicillin 500mg',
            'stock' => 20,
        ]);
        $outOfStock = Medicine::factory()->create([
            'code' => 'MED-100002',
            'name' => 'Vitamin C 1000mg',
            'stock' => 0,
        ]);
        $deleted = Medicine::factory()->create([
            'code' => 'MED-DELETED',
            'name' => 'Deleted Medicine',
        ]);
        $deleted->delete();

        Sanctum::actingAs($this->createUser('DOCTOR'));

        $this->getJson('/api/medicines?q=AMOXICILLIN')
            ->assertOk()
            ->assertJsonPath('data.0.id', $inStock->id)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/medicines?q=MED-100002')
            ->assertOk()
            ->assertJsonPath('data.0.id', $outOfStock->id);

        $this->getJson('/api/medicines?stock_status=in_stock')
            ->assertOk()
            ->assertJsonPath('data.0.id', $inStock->id)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/medicines?stock_status=out_of_stock')
            ->assertOk()
            ->assertJsonPath('data.0.id', $outOfStock->id)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/medicines?q=MED-DELETED')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    /**
     * Verify the doctor role can only read medicines.
     */
    public function test_doctor_is_read_only(): void
    {
        $medicine = Medicine::factory()->create(['stock' => 30]);

        Sanctum::actingAs($this->createUser('DOCTOR'));

        $this->getJson('/api/medicines')
            ->assertOk()
            ->assertJsonPath('data.0.id', $medicine->id);

        $this->getJson("/api/medicines/{$medicine->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $medicine->id);

        $this->postJson('/api/medicines', $this->medicineData())
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: MEDICINES.CREATE');

        $this->patchJson("/api/medicines/{$medicine->id}", [
            'stock' => 999,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: MEDICINES.UPDATE');

        $this->deleteJson("/api/medicines/{$medicine->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: MEDICINES.DELETE');

        $this->patchJson("/api/medicines/{$medicine->id}/stock", [
            'quantity' => 10,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: MEDICINES.ADJUSTSTOCK');

        $this->assertNull($medicine->refresh()->deleted_at);
        $this->assertSame(30, $medicine->stock);
    }

    /**
     * Verify receptionist and cashier roles have no medicine access at all.
     */
    public function test_receptionist_and_cashier_have_no_medicine_access(): void
    {
        $medicine = Medicine::factory()->create();

        foreach (['RECEPTIONIST', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/medicines')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: MEDICINES.FINDALL');

            $this->getJson("/api/medicines/{$medicine->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: MEDICINES.FINDONE');

            $this->postJson('/api/medicines', $this->medicineData())
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: MEDICINES.CREATE');

            $this->patchJson("/api/medicines/{$medicine->id}/stock", [
                'quantity' => 10,
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: MEDICINES.ADJUSTSTOCK');
        }
    }

    /**
     * Verify medicine validation rules and duplicate code rejection.
     */
    public function test_medicine_validation_rejects_invalid_and_duplicate_data(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/medicines', [
            'code' => '',
            'name' => '',
            'unit' => '',
            'price' => -1,
            'stock' => -1,
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'code',
                    'name',
                    'unit',
                    'price',
                    'stock',
                ],
            ]);

        $existing = Medicine::factory()->create(['code' => 'MED-EXISTING']);

        $this->postJson('/api/medicines', $this->medicineData([
            'code' => 'MED-EXISTING',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The code has already been taken.');

        $this->patchJson("/api/medicines/{$existing->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.medicine.0',
                'At least one medicine field must be provided.',
            );

        $this->patchJson("/api/medicines/{$existing->id}", [
            'stock' => -5,
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['stock']]);

        $this->getJson('/api/medicines?stock_status=unknown')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.stock_status.0',
                'The stock status must be in_stock or out_of_stock.',
            );

        $this->patchJson("/api/medicines/{$existing->id}", [
            'code' => 'MED-EXISTING',
            'name' => 'Renamed Medicine',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Renamed Medicine')
            ->assertJsonPath('data.code', 'MED-EXISTING');
    }

    /**
     * Verify unauthenticated requests cannot read medicines.
     */
    public function test_medicine_read_requires_authentication(): void
    {
        $this->getJson('/api/medicines')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify missing medicine IDs return the standard JSON envelope.
     */
    public function test_missing_medicine_returns_404_json(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/medicines/999999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    /**
     * Return a valid medicine request payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function medicineData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'MED-001',
            'name' => 'Paracetamol 500mg',
            'unit' => 'Vỉ',
            'price' => 15000,
            'stock' => 100,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * Create an active user assigned to the requested role.
     */
    private function createUser(string $roleName): User
    {
        return User::factory()
            ->for($this->role($roleName))
            ->create();
    }

    /**
     * Resolve a seeded role by its stable name.
     */
    private function role(string $name): Role
    {
        return Role::query()->where('name', $name)->firstOrFail();
    }
}
