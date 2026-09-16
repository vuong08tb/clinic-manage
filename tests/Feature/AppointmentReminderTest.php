<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Notifications\AppointmentReminderNotification;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time so the "within 24h" window is deterministic.
        Carbon::setTestNow('2026-09-16 10:00:00');

        // Every test needs the DOCTOR role before creating a Doctor.
        Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Bác sĩ',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_scheduled_appointment_within_window_gets_reminder(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $count = app(AppointmentReminderService::class)->sendReminders();

        $this->assertSame(1, $count);

        Notification::assertSentTo(
            $patient,
            AppointmentReminderNotification::class
        );

        $this->assertNotNull($appointment->fresh()->reminded_at);
    }

    public function test_reminder_is_not_sent_twice_for_the_same_appointment(): void
    {
        Notification::fake();

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

        // First run: claims the appointment and sends the reminder.
        $firstCount = $service->sendReminders();
        $this->assertSame(1, $firstCount);

        Notification::assertSentToTimes(
            $patient,
            AppointmentReminderNotification::class,
            1
        );

        // Second run: appointment already claimed, nothing to send.
        $secondCount = $service->sendReminders();
        $this->assertSame(0, $secondCount);

        Notification::assertSentToTimes(
            $patient,
            AppointmentReminderNotification::class,
            1
        );
    }

    public function test_cancelled_appointment_does_not_get_reminder(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_CANCELLED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        $count = app(AppointmentReminderService::class)->sendReminders();

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
        $this->assertNull($appointment->fresh()->reminded_at);
    }

    public function test_appointment_outside_window_does_not_get_reminder(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(48),  // 48h → ngoài 24h
            'reminded_at' => null,
        ]);

        $count = app(AppointmentReminderService::class)->sendReminders(24);

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
        $this->assertNull($appointment->fresh()->reminded_at);
    }

    public function test_appointment_already_reminded_does_not_get_reminder(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => now(),  // ← ĐÃ reminded
        ]);

        $count = app(AppointmentReminderService::class)->sendReminders();

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
        $this->assertNotNull($appointment->fresh()->reminded_at);
    }

    public function test_it_rolls_back_the_claim_when_notification_fails(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => Appointment::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHours(12),
            'reminded_at' => null,
        ]);

        // Force the notification dispatch to throw before delivery.
        Event::listen(NotificationSending::class, function (): void {
            throw new \RuntimeException('simulated delivery failure');
        });

        $count = app(AppointmentReminderService::class)->sendReminders();

        $this->assertSame(0, $count);

        // The claim must be rolled back so the next run retries this appointment.
        $this->assertNull($appointment->fresh()->reminded_at);
    }
}
