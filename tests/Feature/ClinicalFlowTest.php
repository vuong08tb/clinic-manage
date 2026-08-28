<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify the core clinical flow end to end: a receptionist registers a patient,
 * books and confirms an appointment, then a doctor records the examination.
 *
 * Per-endpoint tests seed their prerequisites with factories, so nothing else
 * covers the hand-off between the two roles, or the data actually chaining from
 * one API response into the next request.
 */
class ClinicalFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog the whole flow is authorized against.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Walk the front-desk to consulting-room hand-off one API call at a time.
     */
    public function test_receptionist_registers_patient_then_doctor_records_examination(): void
    {
        $doctor = Doctor::factory()->create();

        // --- 1. Receptionist registers the patient. ---
        Sanctum::actingAs($this->createUser('RECEPTIONIST'));

        $patientId = $this->postJson('/api/patients', [
            'full_name' => 'Nguyen Van An',
            'gender' => 'male',
            'date_of_birth' => '1990-05-20',
            'phone' => '0901234567',
            'email' => 'an.nguyen@example.test',
            'address' => '12 Le Loi, Quan 1',
        ])->assertCreated()->json('data.id');

        // --- 2. Receptionist books an appointment for that patient. ---
        $appointmentId = $this->postJson('/api/appointments', [
            'patient_id' => $patientId,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay()->toISOString(),
            'reason' => 'Persistent cough',
        ])->assertCreated()
            ->assertJsonPath('data.patient_id', $patientId)
            ->assertJsonPath('data.status', Appointment::STATUS_SCHEDULED)
            ->json('data.id');

        // --- 3. Receptionist confirms it: an examination cannot open otherwise. ---
        $this->patchJson("/api/appointments/{$appointmentId}/status", [
            'status' => Appointment::STATUS_CONFIRMED,
        ])->assertOk()
            ->assertJsonPath('data.status', Appointment::STATUS_CONFIRMED);

        // A receptionist runs the front desk but must never record a diagnosis.
        $this->postJson('/api/examinations', [
            'appointment_id' => $appointmentId,
            'diagnosis' => 'Acute pharyngitis',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: EXAMINATIONS.CREATE');

        // --- 4. The doctor takes over and records the examination. ---
        Sanctum::actingAs($doctor->user);

        $this->postJson('/api/examinations', [
            'appointment_id' => $appointmentId,
            'diagnosis' => 'Acute pharyngitis',
            'notes' => 'Antibiotics for five days',
        ])->assertCreated()
            // patient_id and doctor_id are prohibited on the request: they are
            // inherited from the appointment booked back in step 2.
            ->assertJsonPath('data.appointment_id', $appointmentId)
            ->assertJsonPath('data.patient_id', $patientId)
            ->assertJsonPath('data.doctor_id', $doctor->id);

        // Recording the examination closes the appointment in the same transaction.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        // The doctor's side of the hand-off is restricted just as tightly.
        $this->postJson('/api/patients', [
            'full_name' => 'Tran Thi Binh',
            'gender' => 'female',
            'date_of_birth' => '1988-02-11',
            'phone' => '0912345678',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: PATIENTS.CREATE');
    }

    /**
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $roleName): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $roleName)->firstOrFail())
            ->create();
    }
}
