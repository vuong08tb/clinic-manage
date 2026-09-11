<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Notifications\AppointmentReminderNotification;
use App\Services\AppointmentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Bác sĩ',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(1, $count);

        Notification::assertSentTo(
            $patient,
            AppointmentReminderNotification::class
        );

        $this->assertNotNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_cancelled_appointment_does_not_get_reminder(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_CANCELLED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(0, $count);

        Notification::assertNothingSent();

        $this->assertNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_reminder_is_not_sent_twice_for_the_same_appointment(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        // Lần 1
        $firstCount = $service->sendReminders();

        $this->assertSame(1, $firstCount);

        Notification::assertSentToTimes(
            $patient,
            AppointmentReminderNotification::class,
            1
        );

        // Lần 2
        $secondCount = $service->sendReminders();

        $this->assertSame(0, $secondCount);

        Notification::assertSentToTimes(
            $patient,
            AppointmentReminderNotification::class,
            1
        );
    }

    public function test_completed_appointment_does_not_get_reminder(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_COMPLETED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(0, $count);

        Notification::assertNothingSent();

        $this->assertNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_appointment_beyond_24_hours_does_not_get_reminder(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(25),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(0, $count);

        Notification::assertNothingSent();

        $this->assertNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_confirmed_appointment_within_24_hours_gets_reminder(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(1, $count);

        Notification::assertSentTo(
            $patient,
            AppointmentReminderNotification::class
        );

        $this->assertNotNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_appointment_already_reminded_does_not_get_reminder(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => now(),
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(0, $count);

        Notification::assertNothingSent();

        $this->assertNotNull(
            $appointment->fresh()->reminded_at
        );
    }

    public function test_multiple_upcoming_appointments_get_reminders(): void
    {
        Notification::fake();

        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Doctor',
        ]);

        $doctor = Doctor::factory()->create();

        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        Appointment::factory()->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'scheduled_at' => now()->addHours(18),
            'reminded_at' => null,
        ]);

        $service = app(AppointmentReminderService::class);

        $count = $service->sendReminders();

        $this->assertSame(2, $count);

        Notification::assertSentTo(
            $patient1,
            AppointmentReminderNotification::class
        );

        Notification::assertSentTo(
            $patient2,
            AppointmentReminderNotification::class
        );
    }
}
