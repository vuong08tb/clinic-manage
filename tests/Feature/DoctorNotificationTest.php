<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DoctorAppointmentNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DoctorNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Role $doctorRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time so scheduled_at comparisons stay deterministic.
        Carbon::setTestNow('2026-09-18 10:00:00');

        // Every doctor factory needs the DOCTOR role to exist first.
        $this->doctorRole = Role::create([
            'name' => 'DOCTOR',
            'display_name' => 'Bác sĩ',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_doctor_is_notified_when_appointment_is_created(): void
    {
        Notification::fake();

        $doctorUser = User::factory()->create([
            'role_id' => $this->doctorRole->id,
        ]);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addHours(12),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Notification::assertSentTo(
            $doctorUser,
            DoctorAppointmentNotification::class,
            function (DoctorAppointmentNotification $notification) {
                return $notification->eventType === 'created';
            }
        );
    }

    public function test_doctor_is_notified_when_appointment_is_cancelled(): void
    {
        Notification::fake();

        $doctorUser = User::factory()->create([
            'role_id' => $this->doctorRole->id,
        ]);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create();

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addHours(12),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        Notification::assertSentTo(
            $doctorUser,
            DoctorAppointmentNotification::class,
            function (DoctorAppointmentNotification $notification) {
                return $notification->eventType === 'cancelled';
            }
        );
    }
        public function test_doctor_is_notified_when_appointment_is_rescheduled(): void
    {
        Notification::fake();

        $doctorUser = User::factory()->create([
            'role_id' => $this->doctorRole->id,
        ]);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create();

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addHours(12),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $appointment->update([
            'scheduled_at' => now()->addDays(2),
        ]);

        Notification::assertSentTo(
            $doctorUser,
            DoctorAppointmentNotification::class,
            function (DoctorAppointmentNotification $notification) {
                return $notification->eventType === 'rescheduled';
            }
        );
    }
        public function test_notification_payload_contains_appointment_details(): void
    {
        Notification::fake();

        $doctorUser = User::factory()->create([
            'role_id' => $this->doctorRole->id,
        ]);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create([
            'full_name' => 'Nguyễn Thị Bích',
        ]);

        $scheduledAt = now()->addHours(12);

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => $scheduledAt,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        Notification::assertSentTo(
            $doctorUser,
            DoctorAppointmentNotification::class,
            function (DoctorAppointmentNotification $notification) use ($appointment, $patient) {
                $payload = $notification->toArray($notification);

                return $payload['type'] === 'created'
                    && $payload['appointment_id'] === $appointment->id
                    && $payload['patient_name'] === $patient->full_name
                    && $payload['title'] === 'Lịch khám mới'
                    && str_contains($payload['message'], $patient->full_name);
            }
        );
    }
        public function test_doctor_is_not_notified_when_unrelated_field_changes(): void
    {
        $doctorUser = User::factory()->create([
            'role_id' => $this->doctorRole->id,
        ]);

        $doctor = Doctor::factory()->create([
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create();

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addHours(12),
            'status' => Appointment::STATUS_SCHEDULED,
            'reason' => 'Khám tổng quát',
        ]);

        // Bật fake SAU khi tạo appointment — bỏ qua notification 'created'
        Notification::fake();

        // Update field không liên quan
        $appointment->update([
            'reason' => 'Khám chuyên khoa',
        ]);

        // Không notification nào được gửi
        Notification::assertNothingSent();
    }
}