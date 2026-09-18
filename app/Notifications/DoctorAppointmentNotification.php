<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class DoctorAppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
        public string $eventType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage($this->buildPayload());
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->buildPayload());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->buildPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        return [
            'type' => $this->eventType,
            'appointment_id' => $this->appointment->getKey(),
            'patient_name' => $this->appointment->patient?->full_name,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'title' => $this->title(),
            'message' => $this->message(),
        ];
    }

    private function title(): string
    {
        return match ($this->eventType) {
            'created' => 'Lịch khám mới',
            'cancelled' => 'Lịch khám đã huỷ',
            'rescheduled' => 'Lịch khám đổi giờ',
            'upcoming' => 'Sắp tới giờ khám',
            default => 'Thông báo lịch khám',
        };
    }

    private function message(): string
    {
        $patientName = $this->appointment->patient?->full_name ?? 'Bệnh nhân';
        $time = $this->appointment->scheduled_at?->format('H:i d/m/Y') ?? '';

        return match ($this->eventType) {
            'created' => "{$patientName} vừa đặt lịch khám lúc {$time}",
            'cancelled' => "{$patientName} đã huỷ lịch khám lúc {$time}",
            'rescheduled' => "{$patientName} đã đổi lịch khám sang {$time}",
            'upcoming' => "Sắp tới giờ khám {$patientName} lúc {$time}",
            default => "Có cập nhật về lịch khám lúc {$time}",
        };
    }
}
