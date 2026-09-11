<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-appointment-reminders')]
#[Description('Command description')]
class SendAppointmentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AppointmentReminderService $service): int
    {
        $count = $service->sendReminders();
        if ($count === 0) {
            $this->info('No appointments need reminders.');

            return self::SUCCESS;
        }
        $this->info("Queued reminders for {$count} appointments.");

        return self::SUCCESS;

    }
}
