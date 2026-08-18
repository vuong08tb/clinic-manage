<?php

namespace App\Constants;

/**
 * Define response and validation messages for payments.
 */
final class PaymentMessage
{
    public const CREATED = 'Payment created';

    public const INVOICE_NOT_PAYABLE = 'Payments can only be created while the invoice is unpaid.';

    public const AMOUNT_EXCEEDS_REMAINING = 'Amount exceeds the invoice remaining balance of :remaining.';
}
