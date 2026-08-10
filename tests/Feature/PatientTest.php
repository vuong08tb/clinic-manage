<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify patient CRUD, search, validation, soft deletion, and RBAC access.
 */
class PatientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by patient management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify the administrator CRUD flow, generated codes, and soft deletion.
     */
    public function test_admin_can_complete_patient_crud_with_generated_codes_and_soft_delete(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/patients', $this->patientData())
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Patient created',
                'data' => [
                    'code' => 'BN-000001',
                    'full_name' => 'Nguyen Van An',
                    'gender' => 'male',
                    'date_of_birth' => '1995-05-20',
                    'phone' => '0901234567',
                    'email' => 'patient@example.com',
                    'address' => 'Ho Chi Minh City',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'full_name',
                    'gender',
                    'date_of_birth',
                    'phone',
                    'email',
                    'address',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $firstPatient = Patient::query()->where('code', 'BN-000001')->firstOrFail();

        $this->postJson('/api/patients', $this->patientData([
            'full_name' => 'Tran Thi Binh',
            'phone' => '0911111111',
            'email' => 'patient.second@example.com',
        ]))->assertCreated()
            ->assertJsonPath('data.code', 'BN-000002');

        $this->getJson("/api/patients/{$firstPatient->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Patient retrieved')
            ->assertJsonPath('data.code', 'BN-000001');

        $this->patchJson("/api/patients/{$firstPatient->id}", [
            'phone' => '0922222222',
            'address' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Patient updated')
            ->assertJsonPath('data.code', 'BN-000001')
            ->assertJsonPath('data.phone', '0922222222')
            ->assertJsonPath('data.address', null);

        $this->deleteJson("/api/patients/{$firstPatient->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Patient deleted',
                'data' => null,
            ]);

        $this->assertSoftDeleted('patients', ['id' => $firstPatient->id]);
        $this->assertNotNull(Patient::withTrashed()->find($firstPatient->id));

        $this->getJson("/api/patients/{$firstPatient->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * Verify a receptionist can create and update but cannot read or delete patients.
     */
    public function test_receptionist_can_create_and_update_but_cannot_read_or_delete(): void
    {
        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->postJson('/api/patients', $this->patientData())
            ->assertCreated()
            ->assertJsonPath('data.code', 'BN-000001');

        $patient = Patient::query()->firstOrFail();

        $this->patchJson("/api/patients/{$patient->id}", [
            'full_name' => 'Updated By Receptionist',
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'Updated By Receptionist');

        $this->getJson('/api/patients')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PATIENTS.FINDALL');

        $this->getJson("/api/patients/{$patient->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PATIENTS.FINDONE');

        $this->deleteJson("/api/patients/{$patient->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PATIENTS.DELETE');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Verify search by name, phone, and code with pagination metadata.
     */
    public function test_read_role_can_search_patients_and_soft_deleted_rows_are_excluded(): void
    {
        $nguyen = Patient::factory()->create([
            'code' => 'BN-100001',
            'full_name' => 'Nguyen Van Search',
            'phone' => '0905551111',
            'email' => 'nguyen.search@example.com',
        ]);
        $tran = Patient::factory()->create([
            'code' => 'BN-100002',
            'full_name' => 'Tran Thi Search',
            'phone' => '0916662222',
            'email' => 'tran.search@example.com',
        ]);
        $deleted = Patient::factory()->create([
            'code' => 'BN-DELETED',
            'full_name' => 'Deleted Patient',
            'phone' => '0999999999',
            'email' => 'deleted.search@example.com',
        ]);
        $deleted->delete();

        Sanctum::actingAs($this->createUser('DOCTOR'));

        $this->getJson('/api/patients?q=NGUYEN&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nguyen->id)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ]);

        $this->getJson('/api/patients?q=091666')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tran->id);

        $this->getJson('/api/patients?q=BN-100001')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nguyen->id);

        $this->getJson('/api/patients?q=BN-DELETED')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    /**
     * Verify doctor and cashier roles can only read patient profiles.
     */
    public function test_doctor_and_cashier_are_read_only(): void
    {
        $patient = Patient::factory()->create();

        foreach (['DOCTOR', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/patients')
                ->assertOk()
                ->assertJsonPath('data.0.id', $patient->id);

            $this->getJson("/api/patients/{$patient->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $patient->id);

            $this->postJson('/api/patients', $this->patientData([
                'email' => strtolower($roleName).'@blocked.example.com',
            ]))->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PATIENTS.CREATE');

            $this->patchJson("/api/patients/{$patient->id}", [
                'full_name' => 'Forbidden Update',
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PATIENTS.UPDATE');

            $this->deleteJson("/api/patients/{$patient->id}")
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: PATIENTS.DELETE');
        }

        $this->assertSame($patient->full_name, $patient->refresh()->full_name);
        $this->assertNull($patient->deleted_at);
    }

    /**
     * Verify patient validation and immutable generated codes.
     */
    public function test_patient_validation_rejects_invalid_and_duplicate_data(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/patients', [
            'code' => 'CLIENT-CODE',
            'full_name' => '',
            'gender' => 'invalid',
            'date_of_birth' => now()->addDay()->toDateString(),
            'phone' => '',
            'email' => 'not-an-email',
            'address' => str_repeat('x', 256),
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'code',
                    'full_name',
                    'gender',
                    'date_of_birth',
                    'phone',
                    'email',
                    'address',
                ],
            ]);

        $existing = Patient::factory()->create([
            'code' => 'BN-EXISTING',
            'email' => 'unique.patient@example.com',
        ]);

        $this->postJson('/api/patients', $this->patientData([
            'email' => 'unique.patient@example.com',
        ]))->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');

        $this->patchJson("/api/patients/{$existing->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.patient.0',
                'At least one patient field must be provided.',
            );

        $this->patchJson("/api/patients/{$existing->id}", [
            'code' => 'CHANGED-CODE',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The patient code cannot be changed.');

        $this->patchJson("/api/patients/{$existing->id}", [
            'email' => null,
            'address' => null,
        ])->assertOk()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.code', 'BN-EXISTING');
    }

    /**
     * Verify unauthenticated and pharmacist requests cannot read patients.
     */
    public function test_patient_read_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/patients')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        Sanctum::actingAs($this->createUser('PHARMACIST'));

        $this->getJson('/api/patients')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PATIENTS.FINDALL');
    }

    /**
     * Verify missing patient IDs return the standard JSON envelope.
     */
    public function test_missing_patient_returns_404_json(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/patients/999999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    /**
     * Return a valid patient request payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function patientData(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nguyen Van An',
            'gender' => 'male',
            'date_of_birth' => '1995-05-20',
            'phone' => '0901234567',
            'email' => 'patient@example.com',
            'address' => 'Ho Chi Minh City',
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
