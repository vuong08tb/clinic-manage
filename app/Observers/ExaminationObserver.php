<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\Examination;
use App\Services\ActivityLogger;

/**
 * Record audit entries for clinical examination records.
 */
class ExaminationObserver
{
    /**
     * Attributes captured when an examination is opened.
     */
    private const CREATED_ATTRIBUTES = ['appointment_id', 'patient_id', 'doctor_id'];

    /**
     * The only attributes UpdateExaminationRequest allows to change.
     */
    private const CLINICAL_ATTRIBUTES = ['diagnosis', 'notes'];

    /**
     * Create a new examination observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a newly opened examination.
     */
    public function created(Examination $examination): void
    {
        $this->logger->logCreated(
            $examination,
            ActivityLogSubject::EXAMINATION,
            self::CREATED_ATTRIBUTES,
        );
    }

    /**
     * Record edits to the clinical findings.
     */
    public function updated(Examination $examination): void
    {
        $this->logger->logModelChange(
            $examination,
            ActivityLogSubject::EXAMINATION,
            ActivityLogAction::UPDATED,
            self::CLINICAL_ATTRIBUTES,
        );
    }
}
