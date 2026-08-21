<?php

namespace App\Observers;

use App\Constants\ActivityLogSubject;
use App\Models\Prescription;
use App\Services\ActivityLogger;

/**
 * Record audit entries for issued prescriptions.
 */
class PrescriptionObserver
{
    /**
     * Attributes captured when a prescription is issued.
     */
    private const CREATED_ATTRIBUTES = ['examination_id', 'doctor_id'];

    /**
     * Create a new prescription observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a newly issued prescription.
     */
    public function created(Prescription $prescription): void
    {
        $this->logger->logCreated(
            $prescription,
            ActivityLogSubject::PRESCRIPTION,
            self::CREATED_ATTRIBUTES,
        );
    }
}
