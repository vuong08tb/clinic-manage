<?php

namespace App\Observers;

use App\Constants\ActivityLogSubject;
use App\Models\Payment;
use App\Services\ActivityLogger;

/**
 * Record audit entries for payment attempts opened against an invoice.
 *
 * Only creation is observed. Settlement outcomes are recorded by PaymentService, which
 * knows whether PayPal completed the capture and carries the provider identifiers; an
 * updated() handler here would duplicate every one of those entries.
 */
class PaymentObserver
{
    /**
     * Attributes captured when a payment attempt is opened.
     */
    private const CREATED_ATTRIBUTES = ['invoice_id', 'amount', 'method', 'provider_order_id'];

    /**
     * Create a new payment observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a newly opened payment attempt.
     */
    public function created(Payment $payment): void
    {
        $this->logger->logCreated(
            $payment,
            ActivityLogSubject::PAYMENT,
            self::CREATED_ATTRIBUTES,
        );
    }
}
