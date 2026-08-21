<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\Appointment;
use App\Services\ActivityLogger;

/**
 * Record audit entries for appointment lifecycle transitions.
 */
class AppointmentObserver
{
    /**
     * Create a new appointment observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a status transition, ignoring reschedules and other field edits.
     */
    public function updated(Appointment $appointment): void
    {
        $this->logger->logModelChange(
            $appointment,
            ActivityLogSubject::APPOINTMENT,
            ActivityLogAction::STATUS_CHANGED,
            ['status'],
        );
    }
}
