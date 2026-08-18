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

    public const UPDATED = 'Invoice updated';

    public const STATUS_UPDATED = 'Invoice status updated';

    public const SELECTED_EXAMINATION_NOT_FOUND = 'The selected examination does not exist.';

    public const EXAMINATION_ALREADY_INVOICED = 'The examination already has an invoice.';

    public const DISCOUNT_EXCEEDS_SUBTOTAL = 'Discount cannot exceed the invoice subtotal.';

    public const INVOICE_NOT_MODIFIABLE = 'Invoice can only be modified while unpaid.';

    public const ONLY_CANCELLATION_SUPPORTED = 'Only cancelling an invoice is supported via this endpoint.';
}
