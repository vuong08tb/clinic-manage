<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Examination;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\ExaminationService;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Verify examination queries, creation rules, updates, transactions, and RBAC access.
 */
class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by examination management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify doctors can create an examination with appointment-owned context and server time.
     */
    public function test_doctor_can_create_examination_from_confirmed_appointment(): void
    {
        $this->travelTo(Carbon::parse('2026-08-12 10:15:00'));

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->subHour(),
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($doctor->user);

        $this->postJson('/api/examinations', [
            'appointment_id' => $appointment->id,
            'diagnosis' => 'Acute upper respiratory infection',
            'notes' => null,
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Examination created',
                'data' => [
                    'appointment_id' => $appointment->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'diagnosis' => 'Acute upper respiratory infection',
                    'notes' => null,
                    'patient' => [
                        'id' => $patient->id,
                        'code' => $patient->code,
                    ],
                    'doctor' => [
                        'id' => $doctor->id,
                        'user' => [
                            'id' => $doctor->user_id,
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.examined_at', now()->toISOString());

        $this->assertDatabaseHas('examinations', [
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Acute upper respiratory infection',
            'notes' => null,
            'examined_at' => now()->toDateTimeString(),
        ]);
        $this->assertSame(
            Appointment::STATUS_COMPLETED,
            $appointment->refresh()->status,
        );
    }

    /**
     * Verify server-owned examination fields cannot be supplied by clients.
     */
    public function test_store_rejects_server_owned_fields(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $otherPatient = Patient::factory()->create();
        $otherDoctor = Doctor::factory()->create();

        Sanctum::actingAs($appointment->doctor->user);

        $this->postJson('/api/examinations', [
            'appointment_id' => $appointment->id,
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'diagnosis' => 'Client-controlled diagnosis context',
            'examined_at' => now()->subDay()->toISOString(),
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['patient_id', 'doctor_id', 'examined_at'],
            ])
            ->assertJsonPath(
                'errors.patient_id.0',
                'The examination patient is assigned from the appointment.',
            )
            ->assertJsonPath(
                'errors.doctor_id.0',
                'The examination doctor is assigned from the appointment.',
            );

        $this->assertDatabaseMissing('examinations', [
            'appointment_id' => $appointment->id,
        ]);
        $this->assertSame(
            Appointment::STATUS_CONFIRMED,
            $appointment->refresh()->status,
        );
    }

    /**
     * Verify store input requires an existing appointment and a diagnosis.
     */
    public function test_store_validates_appointment_and_diagnosis(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/examinations', [
            'appointment_id' => 999999,
            'diagnosis' => null,
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['appointment_id', 'diagnosis'],
            ])
            ->assertJsonPath(
                'errors.appointment_id.0',
                'The selected appointment does not exist.',
            );
    }

    /**
     * Verify only confirmed appointments can produce examinations.
     */
    public function test_store_rejects_appointments_that_are_not_confirmed(): void
    {
        $doctor = Doctor::factory()->create();

        Sanctum::actingAs($doctor->user);

        foreach ([
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_COMPLETED,
        ] as $status) {
            $appointment = Appointment::factory()->create([
                'doctor_id' => $doctor->id,
                'status' => $status,
            ]);

            $this->postJson('/api/examinations', [
                'appointment_id' => $appointment->id,
                'diagnosis' => "Rejected {$status} appointment",
            ])->assertUnprocessable()
                ->assertJsonPath(
                    'errors.appointment.0',
                    'Only confirmed appointments may be examined.',
                );

            $this->assertDatabaseMissing('examinations', [
                'appointment_id' => $appointment->id,
            ]);
            $this->assertSame($status, $appointment->refresh()->status);
        }
    }

    /**
     * Verify an appointment cannot produce a second examination.
     */
    public function test_store_rejects_duplicate_examination_for_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($appointment->doctor->user);

        $payload = [
            'appointment_id' => $appointment->id,
            'diagnosis' => 'Initial diagnosis',
        ];

        $this->postJson('/api/examinations', $payload)->assertCreated();

        $this->postJson('/api/examinations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.appointment.0',
                'The appointment already has an examination.',
            );

        $this->assertSame(
            1,
            Examination::query()
                ->where('appointment_id', $appointment->id)
                ->count(),
        );
    }

    /**
     * Verify an exception after insert rolls back both examination and appointment changes.
     */
    public function test_creation_transaction_rolls_back_when_a_later_step_fails(): void
    {
        $appointment = Appointment::factory()->create([
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $eventName = 'eloquent.created: '.Examination::class;

        Event::listen($eventName, static function (): never {
            throw new RuntimeException('Forced failure after examination insert.');
        });

        try {
            app(ExaminationService::class)->createFromAppointment([
                'appointment_id' => $appointment->id,
                'diagnosis' => 'Transaction rollback probe',
                'notes' => null,
            ]);

            $this->fail('The forced transaction failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Forced failure after examination insert.',
                $exception->getMessage(),
            );
        } finally {
            Event::forget($eventName);
        }

        $this->assertDatabaseMissing('examinations', [
            'appointment_id' => $appointment->id,
        ]);
        $this->assertSame(
            Appointment::STATUS_CONFIRMED,
            $appointment->refresh()->status,
        );
    }

    /**
     * Verify examination lists combine filters, paginate, and preserve historical patients.
     */
    public function test_index_filters_and_paginates_examinations(): void
    {
        $doctor = Doctor::factory()->create();
        $otherDoctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();

        $older = $this->createExamination($doctor, $patient, [
            'examined_at' => '2026-08-10 09:00:00',
        ]);
        $newer = $this->createExamination($doctor, $patient, [
            'examined_at' => '2026-08-11 09:00:00',
        ]);
        $this->createExamination($doctor, $otherPatient, [
            'examined_at' => '2026-08-12 09:00:00',
        ]);
        $this->createExamination($otherDoctor, $patient, [
            'examined_at' => '2026-08-12 10:00:00',
        ]);
        $patient->delete();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $query = "doctor_id={$doctor->id}&patient_id={$patient->id}&per_page=1";

        $this->getJson("/api/examinations?{$query}&page=1")
            ->assertOk()
            ->assertJsonPath('message', 'Examinations retrieved')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.patient.id', $patient->id)
            ->assertJsonPath('data.0.doctor.user.id', $doctor->user_id)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 2,
                    'last_page' => 2,
                ],
            ]);

        $this->getJson("/api/examinations?{$query}&page=2")
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->id)
            ->assertJsonPath('meta.current_page', 2);
    }

    /**
     * Verify invalid examination list filters return validation errors.
     */
    public function test_index_rejects_invalid_filters(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/examinations?doctor_id=999999&patient_id=999999&page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => ['doctor_id', 'patient_id', 'page', 'per_page'],
            ]);
    }

    /**
     * Verify authorized users can retrieve examination details with clinical context.
     */
    public function test_cashier_can_view_examination_details(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $examination = $this->createExamination($doctor, $patient);
        $patient->delete();

        Sanctum::actingAs($this->createUser('CASHIER'));

        $this->getJson("/api/examinations/{$examination->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Examination retrieved')
            ->assertJsonPath('data.id', $examination->id)
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.doctor.user.id', $doctor->user_id);
    }

    /**
     * Verify doctors can update only mutable clinical examination fields.
     */
    public function test_doctor_can_update_diagnosis_and_notes(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $examination = $this->createExamination($doctor, $patient, [
            'diagnosis' => 'Original diagnosis',
            'notes' => 'Original notes',
        ]);
        $originalContext = $examination->only([
            'appointment_id',
            'patient_id',
            'doctor_id',
            'examined_at',
        ]);

        Sanctum::actingAs($doctor->user);

        $this->patchJson("/api/examinations/{$examination->id}", [
            'diagnosis' => 'Updated diagnosis',
            'notes' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Examination updated')
            ->assertJsonPath('data.diagnosis', 'Updated diagnosis')
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.doctor.user.id', $doctor->user_id);

        $examination->refresh();

        $this->assertSame('Updated diagnosis', $examination->diagnosis);
        $this->assertNull($examination->notes);
        $this->assertSame($originalContext['appointment_id'], $examination->appointment_id);
        $this->assertSame($originalContext['patient_id'], $examination->patient_id);
        $this->assertSame($originalContext['doctor_id'], $examination->doctor_id);
        $this->assertTrue($originalContext['examined_at']->equalTo($examination->examined_at));
    }

    /**
     * Verify update rejects empty input and immutable examination fields.
     */
    public function test_update_rejects_empty_and_immutable_fields(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $examination = $this->createExamination($doctor, $patient);
        $otherAppointment = Appointment::factory()->create();
        $otherDoctor = Doctor::factory()->create();
        $otherPatient = Patient::factory()->create();

        Sanctum::actingAs($doctor->user);

        $this->patchJson("/api/examinations/{$examination->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.examination.0',
                'At least one examination field must be provided.',
            );

        $this->patchJson("/api/examinations/{$examination->id}", [
            'appointment_id' => $otherAppointment->id,
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'examined_at' => now()->subYear()->toISOString(),
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'appointment_id',
                    'patient_id',
                    'doctor_id',
                    'examined_at',
                    'examination',
                ],
            ]);

        $this->assertDatabaseHas('examinations', [
            'id' => $examination->id,
            'appointment_id' => $examination->appointment_id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * Verify each examination action is protected by its mapped permission.
     */
    public function test_receptionist_cannot_access_examination_endpoints(): void
    {
        $examination = Examination::factory()->create();

        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->getJson('/api/examinations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: EXAMINATIONS.FINDALL');

        $this->postJson('/api/examinations', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: EXAMINATIONS.CREATE');

        $this->getJson("/api/examinations/{$examination->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: EXAMINATIONS.FINDONE');

        $this->patchJson("/api/examinations/{$examination->id}", [
            'diagnosis' => 'Forbidden update',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: EXAMINATIONS.UPDATE');
    }

    /**
     * Verify authentication and route model binding return standard API errors.
     */
    public function test_authentication_and_missing_resources_return_json_errors(): void
    {
        $this->getJson('/api/examinations')->assertUnauthorized();

        Sanctum::actingAs($this->createUser('DOCTOR'));

        $this->getJson('/api/examinations/999999')
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);

        $this->patchJson('/api/examinations/999999', [
            'diagnosis' => 'Missing examination',
        ])->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * Create an examination whose patient and doctor match its appointment.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createExamination(
        Doctor $doctor,
        Patient $patient,
        array $attributes = [],
    ): Examination {
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => now()->subDay(),
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        return Examination::factory()->create([
            'appointment_id' => $appointment->id,
            ...$attributes,
        ]);
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
