<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\Invoice;
use App\Services\ActivityLogger;

/**
 * Record audit entries for invoice billing and lifecycle changes.
 */
class InvoiceObserver
{
    /**
     * Amounts captured when an invoice is issued.
     */
    private const BILLING_ATTRIBUTES = ['subtotal', 'discount', 'total'];

    /**
     * Amounts whose later changes are audited.
     *
     * invoice_code is deliberately absent: InvoiceService issues a temporary code and
     * rewrites it in a second save, which would otherwise log a meaningless edit.
     */
    private const MUTABLE_ATTRIBUTES = ['discount', 'total'];

    /**
     * Create a new invoice observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a newly issued invoice.
     */
    public function created(Invoice $invoice): void
    {
        $this->logger->logCreated(
            $invoice,
            ActivityLogSubject::INVOICE,
            self::BILLING_ATTRIBUTES,
        );
    }

    /**
     * Record discount recalculations and lifecycle transitions as separate entries.
     */
    public function updated(Invoice $invoice): void
    {
        $this->logger->logModelChange(
            $invoice,
            ActivityLogSubject::INVOICE,
            ActivityLogAction::UPDATED,
            self::MUTABLE_ATTRIBUTES,
        );

        $this->logger->logModelChange(
            $invoice,
            ActivityLogSubject::INVOICE,
            ActivityLogAction::STATUS_CHANGED,
            ['status'],
        );
    }
}
