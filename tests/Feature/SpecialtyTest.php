<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify specialty CRUD, validation, responses, and RBAC access.
 */
class SpecialtyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by specialty management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    public function test_admin_can_complete_the_specialty_crud_flow(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/specialties', [
            'name' => 'Cardiology',
            'description' => 'Diagnosis and treatment of heart conditions.',
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Specialty created',
                'data' => [
                    'name' => 'Cardiology',
                    'description' => 'Diagnosis and treatment of heart conditions.',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'created_at', 'updated_at'],
            ]);

        $specialty = Specialty::query()->where('name', 'Cardiology')->firstOrFail();

        $this->getJson('/api/specialties?q=cardio&per_page=1')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Specialties retrieved',
                'data' => [
                    ['id' => $specialty->id, 'name' => 'Cardiology'],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ])
            ->assertJsonMissingPath('links');

        $this->getJson("/api/specialties/{$specialty->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Specialty retrieved')
            ->assertJsonPath('data.id', $specialty->id);

        $this->patchJson("/api/specialties/{$specialty->id}", [
            'name' => 'Cardiovascular Medicine',
            'description' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Specialty updated')
            ->assertJsonPath('data.name', 'Cardiovascular Medicine')
            ->assertJsonPath('data.description', null);

        $this->deleteJson("/api/specialties/{$specialty->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Specialty deleted',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
    }

    public function test_store_validation_returns_422_errors_by_field(): void
    {
        Specialty::factory()->create(['name' => 'Neurology']);
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/specialties', [
            'name' => 'Neurology',
            'description' => str_repeat('x', 2001),
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'description'],
            ])
            ->assertJsonMissingPath('data');
    }

    public function test_update_rejects_duplicate_name_and_empty_payload(): void
    {
        $neurology = Specialty::factory()->create(['name' => 'Neurology']);
        $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/specialties/{$cardiology->id}", [
            'name' => $neurology->name,
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);

        $this->patchJson("/api/specialties/{$cardiology->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.specialty.0',
                'At least one specialty field must be provided.',
            );

        $this->assertSame('Cardiology', $cardiology->refresh()->name);
    }

    public function test_receptionist_and_doctor_can_read_specialties(): void
    {
        $specialty = Specialty::factory()->create();

        foreach (['RECEPTIONIST', 'DOCTOR'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/specialties')
                ->assertOk()
                ->assertJsonPath('data.0.id', $specialty->id);

            $this->getJson("/api/specialties/{$specialty->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $specialty->id);
        }
    }

    public function test_read_only_role_cannot_write_specialties(): void
    {
        $specialty = Specialty::factory()->create();
        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->postJson('/api/specialties', [
            'name' => 'Forbidden Specialty',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: SPECIALTIES.CREATE');

        $this->patchJson("/api/specialties/{$specialty->id}", [
            'name' => 'Forbidden Update',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: SPECIALTIES.UPDATE');

        $this->deleteJson("/api/specialties/{$specialty->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: SPECIALTIES.DELETE');

        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
    }

    public function test_roles_without_specialty_permissions_cannot_read(): void
    {
        foreach (['PHARMACIST', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/specialties')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: SPECIALTIES.FINDALL');
        }
    }

    public function test_specialty_endpoints_require_authentication(): void
    {
        $this->getJson('/api/specialties')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify deleting a specialty with an assigned doctor returns a friendly 422 instead of
     * a raw foreign key violation.
     */
    public function test_delete_rejects_specialty_with_assigned_doctors(): void
    {
        $specialty = Specialty::factory()->create();
        Doctor::factory()->create(['specialty_id' => $specialty->id]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->deleteJson("/api/specialties/{$specialty->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.specialty.0',
                'This specialty cannot be deleted because it still has doctors assigned to it.',
            );

        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
    }

    public function test_missing_specialty_returns_404_json(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/specialties/999999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    /**
     * Create an active user assigned to the requested role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $roleName, array $attributes = []): User
    {
        return User::factory()
            ->for($this->role($roleName))
            ->create($attributes);
    }

    /**
     * Resolve a seeded role by its stable name.
     */
    private function role(string $name): Role
    {
        return Role::query()->where('name', $name)->firstOrFail();
    }
}
