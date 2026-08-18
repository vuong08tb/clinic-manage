<?php

namespace App\Constants;

/**
 * Define response and validation messages for payments.
 */
final class PaymentMessage
{
    public const CREATED = 'Payment created';

    public const CAPTURED = 'Payment captured';

    public const CAPTURE_FAILED = 'Payment capture failed';

    public const INVOICE_NOT_PAYABLE = 'Payments can only be created while the invoice is unpaid.';

    public const AMOUNT_EXCEEDS_REMAINING = 'Amount exceeds the invoice remaining balance of :remaining.';

    public const PAYMENT_CANNOT_BE_CAPTURED = 'Only pending payments can be captured.';

    public const CAPTURE_WOULD_EXCEED_TOTAL = 'Capturing this payment would exceed the invoice total.';
}
