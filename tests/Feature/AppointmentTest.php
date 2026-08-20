<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify appointment CRUD, filters, validation, eager loading, and RBAC access.
 */
class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by appointment management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify administrators can create, read, and update scheduled appointments.
     */
    public function test_admin_can_create_read_and_update_a_scheduled_appointment(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData($patient, $doctor))
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Appointment created',
                'data' => [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'status' => Appointment::STATUS_SCHEDULED,
                    'reason' => 'Routine follow-up',
                    'patient' => [
                        'id' => $patient->id,
                        'code' => $patient->code,
                    ],
                    'doctor' => [
                        'id' => $doctor->id,
                        'user_id' => $doctor->user_id,
                        'user' => [
                            'id' => $doctor->user_id,
                        ],
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'patient_id',
                    'doctor_id',
                    'scheduled_at',
                    'status',
                    'reason',
                    'patient',
                    'doctor' => ['user'],
                    'created_at',
                    'updated_at',
                ],
            ]);

        $appointment = Appointment::query()->firstOrFail();

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);

        $this->getJson("/api/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Appointment retrieved')
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.doctor.user.id', $doctor->user_id);

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'scheduled_at' => '2030-06-15T10:30:00+07:00',
            'reason' => null,
        ])->assertOk()
            ->assertJsonPath('message', 'Appointment updated')
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED)
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.doctor.user.id', $doctor->user_id);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'reason' => null,
        ]);
    }

    /**
     * Verify receptionists can create appointments with server-assigned status.
     */
    public function test_receptionist_can_create_an_appointment_with_scheduled_status(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->postJson('/api/appointments', $this->appointmentData($patient, $doctor, [
            'reason' => null,
        ]))->assertCreated()
            ->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED)
            ->assertJsonPath('data.reason', null);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'reason' => null,
        ]);
    }

    /**
     * Verify appointment list filters combine correctly and retain pagination metadata.
     */
    public function test_index_filters_by_doctor_patient_status_and_date(): void
    {
        $doctor = Doctor::factory()->create();
        $otherDoctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();

        $target = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => '2030-06-15 09:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $otherPatient->id,
            'scheduled_at' => '2030-06-15 11:00:00',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $otherDoctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => '2030-06-16 09:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('DOCTOR'));

        $queries = [
            "doctor_id={$doctor->id}&patient_id={$patient->id}",
            "patient_id={$patient->id}&date=2030-06-15",
            "doctor_id={$doctor->id}&status=scheduled",
            "doctor_id={$doctor->id}&patient_id={$patient->id}&status=scheduled&date=2030-06-15",
        ];

        foreach ($queries as $query) {
            $this->getJson("/api/appointments?{$query}&per_page=1&page=1")
                ->assertOk()
                ->assertJsonPath('message', 'Appointments retrieved')
                ->assertJsonPath('data.0.id', $target->id)
                ->assertJsonPath('data.0.patient.id', $patient->id)
                ->assertJsonPath('data.0.doctor.user.id', $doctor->user_id)
                ->assertJson([
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 1,
                        'total' => 1,
                        'last_page' => 1,
                    ],
                ]);
        }
    }

    /**
     * Verify appointment search matches reason, patient details, and doctor details.
     */
    public function test_index_searches_appointments_with_the_model_scope(): void
    {
        $patient = Patient::factory()->create([
            'code' => 'BN-SEARCH-001',
            'full_name' => 'Nguyen Search Patient',
            'phone' => '0905551111',
        ]);
        $doctor = Doctor::factory()->create();
        $doctor->user->update([
            'name' => 'Doctor Scope Search',
            'email' => 'scope.doctor@example.com',
        ]);
        $target = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'reason' => 'Annual cardiovascular review',
        ]);
        Appointment::factory()->create([
            'reason' => 'Unrelated appointment',
        ]);

        Sanctum::actingAs($this->createUser('DOCTOR'));

        foreach ([
            'CARDIOVASCULAR',
            'nguyen search',
            '090555',
            'BN-SEARCH-001',
            'doctor scope',
            'SCOPE.DOCTOR@EXAMPLE.COM',
        ] as $term) {
            $this->getJson('/api/appointments?q='.urlencode($term))
                ->assertOk()
                ->assertJsonPath('data.0.id', $target->id)
                ->assertJsonPath('meta.total', 1);
        }
    }

    /**
     * Verify create and list filters reject invalid input.
     */
    public function test_appointment_validation_rejects_invalid_references_status_and_filters(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', [
            'patient_id' => 999999,
            'doctor_id' => 999999,
            'scheduled_at' => 'not-a-date',
            'status' => Appointment::STATUS_CONFIRMED,
            'reason' => str_repeat('x', 256),
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'patient_id',
                    'doctor_id',
                    'scheduled_at',
                    'status',
                    'reason',
                ],
            ])
            ->assertJsonPath('errors.patient_id.0', 'The selected patient does not exist.')
            ->assertJsonPath('errors.doctor_id.0', 'The selected doctor does not exist.')
            ->assertJsonPath('errors.status.0', 'The appointment status is assigned automatically.');

        $this->getJson('/api/appointments?doctor_id=999999&patient_id=999999&status=invalid&date=15-08-2026&per_page=101')
            ->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'doctor_id',
                    'patient_id',
                    'status',
                    'date',
                    'per_page',
                ],
            ]);
    }

    /**
     * Verify new appointments reject deleted patients while history retains patient details.
     */
    public function test_soft_deleted_patients_are_rejected_for_creation_but_visible_in_history(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $patient->delete();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData($patient, $doctor))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.patient_id.0',
                'The selected patient does not exist.',
            );

        $this->getJson("/api/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.patient.code', $patient->code)
            ->assertJsonPath('data.patient.full_name', $patient->full_name);

        $this->assertSame(
            $patient->id,
            $appointment->fresh()->patient?->id,
        );
    }

    /**
     * Verify appointments cannot be created or rescheduled in the past.
     */
    public function test_create_and_update_reject_past_appointment_times(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);
        $pastTime = now()->subMinute()->toISOString();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData($patient, $doctor, [
            'scheduled_at' => $pastTime,
        ]))->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'The appointment time must be in the future.',
            );

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'scheduled_at' => $pastTime,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'The appointment time must be in the future.',
            );

        $this->assertTrue($appointment->refresh()->scheduled_at->isFuture());
    }

    /**
     * Verify creating an appointment rejects any overlap with an existing 30-minute slot for the same doctor.
     */
    public function test_create_rejects_overlapping_appointment_for_same_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        foreach (['2030-06-15T08:31:00+07:00', '2030-06-15T09:15:00+07:00', '2030-06-15T09:29:00+07:00'] as $overlapping) {
            $patient = Patient::factory()->create();

            $this->postJson('/api/appointments', $this->appointmentData($patient, $doctor, [
                'scheduled_at' => $overlapping,
            ]))->assertUnprocessable()
                ->assertJsonPath(
                    'errors.scheduled_at.0',
                    'The doctor already has an appointment overlapping this 30-minute time slot.',
                );

            $this->assertDatabaseMissing('appointments', [
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
            ]);
        }
    }

    /**
     * Verify adjacent 30-minute slots at the exact boundary do not conflict.
     */
    public function test_create_allows_adjacent_boundary_slots(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        foreach (['2030-06-15T08:30:00+07:00', '2030-06-15T09:30:00+07:00'] as $boundary) {
            $this->postJson('/api/appointments', $this->appointmentData(
                Patient::factory()->create(),
                $doctor,
                ['scheduled_at' => $boundary],
            ))->assertCreated();
        }
    }

    /**
     * Verify a cancelled appointment does not block a new appointment in the same slot.
     */
    public function test_create_ignores_cancelled_appointment_when_checking_conflict(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData(
            Patient::factory()->create(),
            $doctor,
            ['scheduled_at' => '2030-06-15T09:00:00+07:00'],
        ))->assertCreated();
    }

    /**
     * Verify a completed appointment still blocks a new appointment in the same slot.
     */
    public function test_create_treats_completed_appointment_as_conflicting(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData(
            Patient::factory()->create(),
            $doctor,
            ['scheduled_at' => '2030-06-15T09:00:00+07:00'],
        ))->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'The doctor already has an appointment overlapping this 30-minute time slot.',
            );
    }

    /**
     * Verify the same time slot is allowed for two different doctors.
     */
    public function test_create_allows_same_time_slot_for_different_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        $otherDoctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->postJson('/api/appointments', $this->appointmentData(
            Patient::factory()->create(),
            $otherDoctor,
            ['scheduled_at' => '2030-06-15T09:00:00+07:00'],
        ))->assertCreated();
    }

    /**
     * Verify rescheduling an appointment onto another appointment's slot for the same doctor is rejected.
     */
    public function test_update_rejects_overlap_with_another_appointment_of_same_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-16T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $originalScheduledAt = $appointment->scheduled_at;

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'scheduled_at' => '2030-06-15T09:15:00+07:00',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'The doctor already has an appointment overlapping this 30-minute time slot.',
            );

        $this->assertTrue($appointment->refresh()->scheduled_at->equalTo($originalScheduledAt));
    }

    /**
     * Verify updating an appointment does not treat itself as a conflicting appointment.
     */
    public function test_update_excludes_itself_from_conflict_check(): void
    {
        $doctor = Doctor::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $originalScheduledAt = $appointment->scheduled_at;

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'scheduled_at' => '2030-06-15T09:15:00+07:00',
        ])->assertOk();

        $this->assertFalse($appointment->refresh()->scheduled_at->equalTo($originalScheduledAt));
    }

    /**
     * Verify updating only the reason does not trigger the conflict check.
     */
    public function test_update_reason_only_does_not_trigger_conflict_check(): void
    {
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'reason' => 'Updated reason only',
        ])->assertOk()
            ->assertJsonPath('data.reason', 'Updated reason only');
    }

    /**
     * Verify update accepts only scheduling fields and requires a non-empty payload.
     */
    public function test_update_rejects_immutable_fields_and_empty_payloads(): void
    {
        $appointment = Appointment::factory()->create();
        $otherPatient = Patient::factory()->create();
        $otherDoctor = Doctor::factory()->create();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/appointments/{$appointment->id}", [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.appointment.0',
                'At least one appointment field must be provided.',
            );

        $this->patchJson("/api/appointments/{$appointment->id}", [
            'patient_id' => $otherPatient->id,
            'doctor_id' => $otherDoctor->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'reason' => 'Allowed field keeps the payload non-empty.',
        ])->assertUnprocessable()
            ->assertJsonStructure([
                'errors' => [
                    'patient_id',
                    'doctor_id',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $appointment->doctor_id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Verify confirmed, cancelled, and completed appointments cannot be updated.
     */
    public function test_only_scheduled_appointments_may_be_updated(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        foreach ([
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_COMPLETED,
        ] as $status) {
            $appointment = Appointment::factory()->create([
                'status' => $status,
                'reason' => 'Original reason',
            ]);

            $this->patchJson("/api/appointments/{$appointment->id}", [
                'reason' => 'Forbidden update',
            ])->assertUnprocessable()
                ->assertJsonPath(
                    'errors.appointment.0',
                    'Only scheduled appointments may be updated.',
                );

            $this->assertSame('Original reason', $appointment->refresh()->reason);
        }
    }

    /**
     * Verify administrators can apply every allowed appointment status transition.
     */
    public function test_admin_can_apply_all_allowed_status_transitions(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $transitions = [
            [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED],
            [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CANCELLED],
            [Appointment::STATUS_CONFIRMED, Appointment::STATUS_COMPLETED],
            [Appointment::STATUS_CONFIRMED, Appointment::STATUS_CANCELLED],
        ];

        foreach ($transitions as [$from, $to]) {
            $appointment = Appointment::factory()->create(['status' => $from]);

            $this->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => $to,
            ])->assertOk()
                ->assertJsonPath('message', 'Appointment status updated')
                ->assertJsonPath('data.status', $to)
                ->assertJsonPath('data.patient.id', $appointment->patient_id)
                ->assertJsonPath('data.doctor.user.id', $appointment->doctor->user_id);

            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id,
                'status' => $to,
            ]);
        }
    }

    /**
     * Verify every disallowed appointment status transition returns a clear error.
     */
    public function test_invalid_status_transitions_return_clear_validation_errors(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $invalidTransitions = [
            Appointment::STATUS_SCHEDULED => [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_COMPLETED,
            ],
            Appointment::STATUS_CONFIRMED => [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ],
            Appointment::STATUS_COMPLETED => Appointment::STATUSES,
            Appointment::STATUS_CANCELLED => Appointment::STATUSES,
        ];

        foreach ($invalidTransitions as $from => $targets) {
            foreach ($targets as $to) {
                $appointment = Appointment::factory()->create(['status' => $from]);

                $this->patchJson("/api/appointments/{$appointment->id}/status", [
                    'status' => $to,
                ])->assertUnprocessable()
                    ->assertJsonPath(
                        'errors.status.0',
                        "The appointment status cannot transition from {$from} to {$to}.",
                    );

                $this->assertSame($from, $appointment->refresh()->status);
            }
        }
    }

    /**
     * Verify the update status endpoint validates input and missing appointments.
     */
    public function test_update_status_validates_payload_and_missing_appointments(): void
    {
        $appointment = Appointment::factory()->create();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->patchJson("/api/appointments/{$appointment->id}/status", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['status']]);

        $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.status.0',
                'The status must be scheduled, confirmed, cancelled, or completed.',
            );

        $this->patchJson('/api/appointments/999999/status', [
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');

        $this->assertSame(
            Appointment::STATUS_SCHEDULED,
            $appointment->refresh()->status,
        );
    }

    /**
     * Verify the update status endpoint enforces authentication and role permissions.
     */
    public function test_update_status_enforces_authentication_and_permission_matrix(): void
    {
        $appointment = Appointment::factory()->create();

        $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $this->patchJson("/api/appointments/{$appointment->id}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED);

        foreach (['DOCTOR', 'CASHIER', 'PHARMACIST'] as $roleName) {
            $blockedAppointment = Appointment::factory()->create();

            Sanctum::actingAs($this->createUser($roleName));

            $this->patchJson("/api/appointments/{$blockedAppointment->id}/status", [
                'status' => Appointment::STATUS_CONFIRMED,
            ])->assertForbidden()
                ->assertJsonPath(
                    'message',
                    'Missing permission: APPOINTMENTS.UPDATESTATUS',
                );

            $this->assertSame(
                Appointment::STATUS_SCHEDULED,
                $blockedAppointment->refresh()->status,
            );
        }
    }

    /**
     * Verify doctor and cashier roles can read but cannot mutate appointments.
     */
    public function test_doctor_and_cashier_are_read_only(): void
    {
        $appointment = Appointment::factory()->create();

        foreach (['DOCTOR', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/appointments')
                ->assertOk()
                ->assertJsonPath('data.0.id', $appointment->id);

            $this->getJson("/api/appointments/{$appointment->id}")
                ->assertOk()
                ->assertJsonPath('data.id', $appointment->id);

            $this->postJson('/api/appointments', $this->appointmentData(
                $appointment->patient,
                $appointment->doctor,
            ))->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: APPOINTMENTS.CREATE');

            $this->patchJson("/api/appointments/{$appointment->id}", [
                'reason' => 'Forbidden update',
            ])->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: APPOINTMENTS.UPDATE');
        }

        $this->assertNotSame('Forbidden update', $appointment->refresh()->reason);
    }

    /**
     * Verify appointment endpoints enforce authentication and role permissions.
     */
    public function test_appointment_read_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/appointments')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        Sanctum::actingAs($this->createUser('PHARMACIST'));

        $this->getJson('/api/appointments')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: APPOINTMENTS.FINDALL');
    }

    /**
     * Verify missing appointments and unsupported deletion return JSON errors.
     */
    public function test_missing_appointment_and_delete_route_return_standard_errors(): void
    {
        $appointment = Appointment::factory()->create();

        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/appointments/999999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);

        $this->deleteJson("/api/appointments/{$appointment->id}")
            ->assertMethodNotAllowed()
            ->assertJsonPath('message', 'Method not allowed.');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    /**
     * Return a valid appointment payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function appointmentData(
        Patient $patient,
        Doctor $doctor,
        array $overrides = [],
    ): array {
        return array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => '2030-06-15T09:00:00+07:00',
            'reason' => 'Routine follow-up',
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
