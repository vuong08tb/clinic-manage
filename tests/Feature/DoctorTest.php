<?php

namespace Tests\Feature;

use App\Models\Appointment;
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
 * Verify doctor CRUD, search, validation, role invariants, and RBAC access.
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
     * Verify the administrator CRUD flow with eager-loaded relations.
     */
    public function test_admin_can_complete_doctor_crud(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $doctorUser = $this->createUser('DOCTOR');
        $specialty = Specialty::factory()->create(['name' => 'Cardiology']);

        $this->postJson('/api/doctors', $this->doctorData([
            'user_id' => $doctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-0001',
            'bio' => 'Cardiologist with ten years of clinical experience.',
        ]))->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Doctor created',
                'data' => [
                    'user_id' => $doctorUser->id,
                    'specialty_id' => $specialty->id,
                    'license_number' => 'VN-DOC-0001',
                    'bio' => 'Cardiologist with ten years of clinical experience.',
                    'user' => [
                        'id' => $doctorUser->id,
                        'name' => $doctorUser->name,
                        'email' => $doctorUser->email,
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

        $doctor = Doctor::query()->where('license_number', 'VN-DOC-0001')->firstOrFail();

        $this->getJson("/api/doctors/{$doctor->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Doctor retrieved')
            ->assertJsonPath('data.id', $doctor->id)
            ->assertJsonPath('data.specialty.name', 'Cardiology');

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'license_number' => 'VN-DOC-0001-UPDATED',
            'bio' => 'Updated professional biography.',
        ])->assertOk()
            ->assertJsonPath('message', 'Doctor updated')
            ->assertJsonPath('data.license_number', 'VN-DOC-0001-UPDATED')
            ->assertJsonPath('data.bio', 'Updated professional biography.');

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

        $this->getJson("/api/doctors/{$doctor->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * Verify receptionist and doctor roles can only read doctor profiles.
     */
    public function test_receptionist_and_doctor_are_read_only(): void
    {
        $doctor = Doctor::factory()->create();

        foreach (['RECEPTIONIST', 'DOCTOR'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/doctors')
                ->assertOk()
                ->assertJsonPath('data.0.id', $doctor->id);

            $this->getJson("/api/doctors/{$doctor->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $doctor->id);

            $this->postJson('/api/doctors', $this->doctorData())
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.CREATE');

            $this->patchJson("/api/doctors/{$doctor->id}", [
                'bio' => 'Forbidden update',
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.UPDATE');

            $this->deleteJson("/api/doctors/{$doctor->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.DELETE');
        }

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    /**
     * Verify pharmacist and cashier roles cannot access doctor endpoints.
     */
    public function test_pharmacist_and_cashier_have_no_doctor_access(): void
    {
        $doctor = Doctor::factory()->create();

        foreach (['PHARMACIST', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/doctors')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.FINDALL');

            $this->getJson("/api/doctors/{$doctor->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: DOCTORS.FINDONE');
        }
    }

    /**
     * Verify search by name, email, license number, and bio with pagination metadata.
     */
    public function test_admin_can_search_and_filter_doctors(): void
    {
        $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
        $neurology = Specialty::factory()->create(['name' => 'Neurology']);

        $cardio = Doctor::factory()->create([
            'specialty_id' => $cardiology->id,
            'license_number' => 'VN-DOC-CARDIO',
            'bio' => 'Expert in heart conditions.',
        ]);
        $neuro = Doctor::factory()->create([
            'specialty_id' => $neurology->id,
            'license_number' => 'VN-DOC-NEURO',
            'bio' => 'Expert in brain conditions.',
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/doctors?q=CARDIO&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $cardio->id)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ]);

        $this->getJson("/api/doctors?specialty_id={$neurology->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $neuro->id)
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/doctors?q='.urlencode($cardio->user->email))
            ->assertOk()
            ->assertJsonPath('data.0.id', $cardio->id);
    }

    /**
     * Verify creation validation rejects invalid, missing, and duplicate data.
     */
    public function test_doctor_validation_rejects_invalid_and_duplicate_data(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/doctors', [
            'user_id' => 999999,
            'specialty_id' => 999999,
            'license_number' => '',
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['user_id', 'specialty_id', 'license_number'],
            ]);

        $existing = Doctor::factory()->create(['license_number' => 'VN-DOC-EXISTING']);
        $anotherDoctorUser = $this->createUser('DOCTOR');
        $specialty = Specialty::factory()->create();

        $this->postJson('/api/doctors', $this->doctorData([
            'user_id' => $anotherDoctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-EXISTING',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.license_number.0',
                'The license number has already been taken.',
            );

        $this->postJson('/api/doctors', $this->doctorData([
            'user_id' => $existing->user_id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-ANOTHER',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user already has a doctor profile.',
            );

        $nonDoctorUser = $this->createUser('RECEPTIONIST');

        $this->postJson('/api/doctors', $this->doctorData([
            'user_id' => $nonDoctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'VN-DOC-WRONG-ROLE',
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user must have the DOCTOR role.',
            );

        $this->assertDatabaseMissing('doctors', ['license_number' => 'VN-DOC-WRONG-ROLE']);
    }

    /**
     * Verify update validation enforces the doctor-role invariant and empty-body rejection.
     */
    public function test_doctor_update_validation_and_role_invariant(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $doctor = Doctor::factory()->create(['license_number' => 'VN-DOC-ORIGINAL']);

        $this->patchJson("/api/doctors/{$doctor->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.doctor.0',
                'At least one doctor field must be provided.',
            );

        $otherDoctor = Doctor::factory()->create(['license_number' => 'VN-DOC-OTHER']);

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'license_number' => 'VN-DOC-OTHER',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.license_number.0',
                'The license number has already been taken.',
            );

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'user_id' => $otherDoctor->user_id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user already has a doctor profile.',
            );

        $nonDoctorUser = $this->createUser('RECEPTIONIST');

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'user_id' => $nonDoctorUser->id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.user_id.0',
                'The selected user must have the DOCTOR role.',
            );

        $this->assertSame('VN-DOC-ORIGINAL', $doctor->refresh()->license_number);

        $newDoctorUser = $this->createUser('DOCTOR');

        $this->patchJson("/api/doctors/{$doctor->id}", [
            'user_id' => $newDoctorUser->id,
        ])->assertOk()
            ->assertJsonPath('data.user_id', $newDoctorUser->id);
    }

    /**
     * Verify unauthenticated and out-of-scope requests are rejected.
     */
    public function test_doctor_access_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/doctors')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify deleting a doctor with dependent appointments returns a friendly 422 instead of
     * a raw foreign key violation.
     */
    public function test_delete_rejects_doctor_with_dependent_records(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create(['doctor_id' => $doctor->id]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->deleteJson("/api/doctors/{$doctor->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.doctor.0',
                'This doctor cannot be deleted because they have appointments, examinations, or prescriptions on record.',
            );

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    /**
     * Verify missing doctor IDs return the standard JSON envelope.
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
     * Return a valid doctor request payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function doctorData(array $overrides = []): array
    {
        return array_merge([
            'user_id' => $this->createUser('DOCTOR')->id,
            'specialty_id' => Specialty::factory()->create()->id,
            'license_number' => fake()->unique()->bothify('VN-DOC-####-????'),
            'bio' => 'General practitioner.',
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
