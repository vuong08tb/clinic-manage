<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for invoices.
 */
final class InvoiceMessage
{
    public const LIST_RETRIEVED = 'Invoices retrieved';

    public const RETRIEVED = 'Invoice retrieved';

    public const CREATED = 'Invoice created';

    public const SELECTED_EXAMINATION_NOT_FOUND = 'The selected examination does not exist.';

    public const EXAMINATION_ALREADY_INVOICED = 'The examination already has an invoice.';

    public const DISCOUNT_EXCEEDS_SUBTOTAL = 'Discount cannot exceed the invoice subtotal.';
}
