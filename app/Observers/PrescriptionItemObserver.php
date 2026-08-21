<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\PrescriptionItem;
use App\Services\ActivityLogger;

/**
 * Record audit entries for the medicine lines of a prescription.
 *
 * These entries describe the prescription line itself. The matching stock movement is
 * audited separately against the medicine subject by PrescriptionService.
 */
class PrescriptionItemObserver
{
    /**
     * Attributes captured when a medicine line is added or removed.
     */
    private const LINE_ATTRIBUTES = ['prescription_id', 'medicine_id', 'quantity'];

    /**
     * The attributes PrescriptionService allows an existing line to change.
     */
    private const MUTABLE_ATTRIBUTES = ['quantity', 'dosage', 'usage_instruction'];

    /**
     * Create a new prescription item observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a medicine line added to a prescription.
     */
    public function created(PrescriptionItem $item): void
    {
        $this->logger->logCreated(
            $item,
            ActivityLogSubject::PRESCRIPTION_ITEM,
            self::LINE_ATTRIBUTES,
        );
    }

    /**
     * Record edits to an existing medicine line.
     */
    public function updated(PrescriptionItem $item): void
    {
        $this->logger->logModelChange(
            $item,
            ActivityLogSubject::PRESCRIPTION_ITEM,
            ActivityLogAction::UPDATED,
            self::MUTABLE_ATTRIBUTES,
        );
    }

    /**
     * Record a medicine line removed from a prescription.
     */
    public function deleted(PrescriptionItem $item): void
    {
        $this->logger->logDeleted(
            $item,
            ActivityLogSubject::PRESCRIPTION_ITEM,
            self::LINE_ATTRIBUTES,
        );
    }
}
