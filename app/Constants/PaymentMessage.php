<?php

namespace App\Constants;

/**
 * Define response and validation messages for payments.
 */
final class PaymentMessage
{
    public const LIST_RETRIEVED = 'Payments retrieved';

    public const CREATED = 'Payment created';

    public const CAPTURED = 'Payment captured';

    public const CAPTURE_FAILED = 'Payment capture failed';

    public const CLIENT_TOKEN_RETRIEVED = 'PayPal client token retrieved';

    public const INVOICE_NOT_PAYABLE = 'Payments can only be created while the invoice is unpaid.';

    public const AMOUNT_EXCEEDS_REMAINING = 'Amount exceeds the invoice remaining balance of :remaining.';

    public const PAYMENT_CANNOT_BE_CAPTURED = 'Only pending payments can be captured.';

    public const CAPTURE_WOULD_EXCEED_TOTAL = 'Capturing this payment would exceed the invoice total.';

    public const SELECTED_INVOICE_NOT_FOUND = 'The selected invoice does not exist.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';
}
