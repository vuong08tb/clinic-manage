<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\Appointment;
use App\Services\ActivityLogger;
use App\Services\DoctorNotificationService;

/**
 * Record audit entries and notify the doctor on appointment lifecycle events.
 */
class AppointmentObserver
{
    /**
     * Create a new appointment observer instance.
     */
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly DoctorNotificationService $notifier,
    ) {}

    /**
     * Notify the assigned doctor when a new appointment is created.
     */
    public function created(Appointment $appointment): void
    {
        $this->notifier->notifyDoctor($appointment, 'created');
    }

    /**
     * Record a status transition and notify the doctor on cancellation or reschedule.
     */
    public function updated(Appointment $appointment): void
    {
        $this->logger->logModelChange(
            $appointment,
            ActivityLogSubject::APPOINTMENT,
            ActivityLogAction::STATUS_CHANGED,
            ['status'],
        );

        if ($appointment->wasChanged('status')
            && $appointment->status === Appointment::STATUS_CANCELLED) {
            $this->notifier->notifyDoctor($appointment, 'cancelled');
        }

        if ($appointment->wasChanged('scheduled_at')) {
            $this->notifier->notifyDoctor($appointment, 'rescheduled');
        }
    }
}
