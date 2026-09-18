<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\DoctorAppointmentNotification;
use Illuminate\Support\Facades\Log;

class DoctorNotificationService
{
    /**
     * Notify the doctor assigned to the appointment about an event.
     *
     * The doctor may not exist (appointment created without doctor), or the
     * doctor may not have a user account yet. In both cases the notification
     * is skipped and a warning is logged instead of throwing.
     */
    public function notifyDoctor(Appointment $appointment, string $eventType): void
    {
        $doctorUser = $appointment->doctor?->user;

        if (! $doctorUser) {
            Log::warning('Cannot notify doctor: no user linked to appointment', [
                'appointment_id' => $appointment->getKey(),
                'event_type' => $eventType,
            ]);

            return;
        }

        $doctorUser->notify(
            new DoctorAppointmentNotification($appointment, $eventType)
        );
    }
}
