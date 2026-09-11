<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Carbon\Carbon;

class AppointmentReminderService
{
    /**
     * Send reminders for upcoming appointments.
     */
    public function sendReminders(int $hours = 24): int
    {
        $now = Carbon::now();
        $targetTime = $now->copy()->addHours($hours);

        $appointment = Appointment::query()
            ->with(['Patient', 'doctor.user'])
            ->whereIn('status', [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ])
            ->whereNull('reminded_at')
            ->whereBetween('scheduled_at', [
                $now,
                $targetTime,
            ])
            ->get();
        $count = 0;
        foreach ($appointment as $appointment) {
            if (! $appointment->patient) {
                continue;
            }
            $appointment->patient->notify(
                new AppointmentReminderNotification($appointment)
            );
            $appointment->update([
                'reminded_at' => Carbon::now(),
            ]);

            $count++;

        }

        return $count;
    }
}
