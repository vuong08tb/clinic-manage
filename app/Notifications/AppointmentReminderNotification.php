<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Appointment $appointment)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $doctorName = $this->appointment->doctor?->user?->name ?? 'Bác sĩ';

        return (new MailMessage)
            ->subject('Nhắc lịch khám')
            ->greeting('Xin chào '.$notifiable->full_name.',')
            ->line('Bạn có một lịch khám sắp tới.')
            ->line('Bác sĩ: '.$doctorName)
            ->line(
                'Thời gian: '
                .$this->appointment->scheduled_at->format('H:i d/m/Y')
            )
            ->line(
                'Lý do khám: '
                .($this->appointment->reason ?? 'Không có')
            )
            ->line('Vui lòng đến phòng khám đúng giờ.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $doctorName = $this->appointment->doctor?->user?->name ?? 'Bác sĩ';

        return [
            //
            'appointment_id' => $this->appointment->id,
            'doctor_name' => $doctorName,
            'scheduled_at' => $this->appointment->scheduled_at->toDateTimeString(),
            'reason' => $this->appointment->reason,
            'message' => sprintf(
                'Bạn có lịch khám lúc %s với %s.',
                $this->appointment->scheduled_at->format('H:i d/m/Y'),
                $doctorName
            ),
        ];
    }
}
