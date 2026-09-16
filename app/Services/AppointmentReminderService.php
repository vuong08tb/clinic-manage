<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentReminderService
{
    public function sendReminders(int $hours = 24): int
    {
        $now = Carbon::now();
        $targetTime = $now->copy()->addHours($hours);
        $count = 0;

        Appointment::query()
            ->with(['patient', 'doctor.user'])
            ->whereIn('status', [
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_CONFIRMED,
            ])
            ->whereNull('reminded_at')
            ->whereHas('patient')
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $targetTime)
            ->chunkById(500, function ($appointments) use (&$count, $now) {
                foreach ($appointments as $appointment) {

                    // Step A: Atomic claim — stamp the appointment first.
                    $claimed = Appointment::query()
                        ->whereKey($appointment->id)
                        ->whereNull('reminded_at')
                        ->update(['reminded_at' => $now]);

                    // Step B: Another worker already claimed it.
                    if ($claimed === 0) {
                        continue;
                    }

                    // Step C: We won the claim — send the notification.
                    try {
                        $appointment->patient->notify(
                            new AppointmentReminderNotification($appointment)
                        );
                        $count++;
                    } catch (\Throwable $e) {
                        // Step D: Roll back the claim so the next run retries this appointment.
                        Appointment::query()
                            ->whereKey($appointment->id)
                            ->update(['reminded_at' => null]);

                        Log::error('Failed to send reminder', [
                            'appointment_id' => $appointment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('Appointment reminders dispatched', [
            'count' => $count,
            'hours' => $hours,
        ]);

        return $count;
    }
}
