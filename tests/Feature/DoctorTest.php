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
 * Verify doctor CRUD, business rules, responses, and RBAC access.
 */
class DoctorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by doctor management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify the complete administrator CRUD flow and response structure.
     */
    public function test_admin_can_complete_the_doctor_crud_flow(): void
    {
        $doctorUser = $this->createUser('DOCTOR', [
            'name' => 'Cardiology Doctor',
            'email' => 'cardiology.doctor@example.com',
        ]);
        $specialty = Specialty::factory()->create(['name' => 'Cardiology']);
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/doctors', [
            'user_id' => $doctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-0001',
            'bio' => 'Cardiologist with clinical experience.',
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Doctor created',
                'data' => [
                    'user_id' => $doctorUser->id,
                    'specialty_id' => $specialty->id,
                    'license_number' => 'VN-DOC-0001',
                    'user' => [
                        'id' => $doctorUser->id,
                        'name' => 'Cardiology Doctor',
                    ],
                    'specialty' => [
                        'id' => $specialty->id,
                        'name' => 'Cardiology',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'specialty_id',
                    'license_number',
                    'bio',
                    'user' => ['id', 'name', 'email'],
                    'specialty' => ['id', 'name'],
                    'created_at',
                    'updated_at',
                ],
            ]);

        $doctor = Doctor::query()->where('user_id', $doctorUser->id)->firstOrFail();

        $this->getJson("/api/doctors?q=cardiology&specialty_id={$specialty->id}&per_page=1")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Doctors retrieved',
                'data' => [
                    ['id' => $doctor->id, 'license_number' => 'VN-DOC-0001'],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ]);

        $this->getJson("/api/doctors/{$doctor->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Doctor retrieved')
            ->assertJsonPath('data.user.email', 'cardiology.doctor@example.com');

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'license_number' => 'VN-DOC-0001-UPDATED',
            'bio' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Doctor updated')
            ->assertJsonPath('data.license_number', 'VN-DOC-0001-UPDATED')
            ->assertJsonPath('data.bio', null);

        $this->deleteJson("/api/doctors/{$doctor->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Doctor deleted',
                'data' => null,
            ]);

        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
        $this->assertDatabaseHas('users', ['id' => $doctorUser->id]);
        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
    }

    /**
     * Verify that a non-doctor user cannot receive a doctor profile.
     */
    public function test_create_rejects_a_user_without_the_doctor_role(): void
    {
        $receptionist = $this->createUser('RECEPTIONIST');
        $specialty = Specialty::factory()->create();
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/doctors', [
            'user_id' => $receptionist->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-WRONG-ROLE',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The selected user must have the DOCTOR role.')
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user must have the DOCTOR role.',
            );

        $this->assertDatabaseCount('doctors', 0);
    }

    /**
     * Verify that assigning a doctor profile to a non-doctor user rolls back.
     */
    public function test_update_rejects_a_user_without_the_doctor_role(): void
    {
        $doctorUser = $this->createUser('DOCTOR');
        $receptionist = $this->createUser('RECEPTIONIST');
        $doctor = Doctor::factory()
            ->for($doctorUser)
            ->for(Specialty::factory())
            ->create();
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'user_id' => $receptionist->id,
            'bio' => 'This mutation must be rolled back.',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user must have the DOCTOR role.',
            );

        $doctor->refresh();
        $this->assertSame($doctorUser->id, $doctor->user_id);
        $this->assertNotSame('This mutation must be rolled back.', $doctor->bio);
    }

    /**
     * Verify uniqueness rules and the non-empty update contract.
     */
    public function test_validation_rejects_duplicate_fields_and_empty_updates(): void
    {
        $firstUser = $this->createUser('DOCTOR');
        $secondUser = $this->createUser('DOCTOR');
        $specialty = Specialty::factory()->create();
        $firstDoctor = Doctor::factory()
            ->for($firstUser)
            ->for($specialty)
            ->create(['license_number' => 'VN-DOC-UNIQUE']);
        $secondDoctor = Doctor::factory()
            ->for($secondUser)
            ->for($specialty)
            ->create(['license_number' => 'VN-DOC-SECOND']);
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/doctors', [
            'user_id' => $firstUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-UNIQUE',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['user_id', 'license_number']]);

        $this->patchJson("/api/doctors/{$secondDoctor->id}", [
            'license_number' => $firstDoctor->license_number,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.license_number.0',
                'The license number has already been taken.',
            );

        $this->patchJson("/api/doctors/{$secondDoctor->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.doctor.0',
                'At least one doctor field must be provided.',
            );
    }

    /**
     * Verify doctor profiles keep their user accounts on the DOCTOR role.
     */
    public function test_user_with_a_doctor_profile_cannot_change_to_another_role(): void
    {
        $doctorUser = $this->createUser('DOCTOR');
        Doctor::factory()
            ->for($doctorUser)
            ->for(Specialty::factory())
            ->create();
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/users/{$doctorUser->id}", [
            'role_id' => $this->role('RECEPTIONIST')->id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.role_id.0',
                'A user with a doctor profile must keep the DOCTOR role.',
            );

        $this->assertSame('DOCTOR', $doctorUser->refresh()->role->name);
    }

    /**
     * Verify read-only roles can list and view doctor profiles.
     */
    public function test_receptionist_and_doctor_can_read_doctors(): void
    {
        $profile = Doctor::factory()->create();

        foreach (['RECEPTIONIST', 'DOCTOR'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/doctors')
                ->assertOk()
                ->assertJsonPath('data.0.id', $profile->id);

            $this->getJson("/api/doctors/{$profile->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $profile->id);
        }
    }

    /**
     * Verify a read-only role cannot mutate doctor profiles.
     */
    public function test_read_only_role_cannot_write_doctors(): void
    {
        $profile = Doctor::factory()->create();
        $newDoctorUser = $this->createUser('DOCTOR');
        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->postJson('/api/doctors', [
            'user_id' => $newDoctorUser->id,
            'specialty_id' => $profile->specialty_id,
            'license_number' => 'VN-DOC-FORBIDDEN',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: DOCTORS.CREATE');

        $this->patchJson("/api/doctors/{$profile->id}", [
            'bio' => 'Forbidden update',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: DOCTORS.UPDATE');

        $this->deleteJson("/api/doctors/{$profile->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: DOCTORS.DELETE');

        $this->assertDatabaseHas('doctors', ['id' => $profile->id]);
    }

    /**
     * Verify roles without doctor permissions cannot read profiles.
     */
    public function test_roles_without_doctor_permissions_cannot_read(): void
    {
        foreach (['PHARMACIST', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/doctors')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.FINDALL');
        }
    }

    /**
     * Verify doctor endpoints require authentication.
     */
    public function test_doctor_endpoints_require_authentication(): void
    {
        $this->getJson('/api/doctors')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify route model binding returns the standard missing-resource envelope.
     */
    public function test_missing_doctor_returns_404_json(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/doctors/999999')
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
