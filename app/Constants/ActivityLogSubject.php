<?php

namespace App\Constants;

/**
 * Define the morph aliases stored in activity_logs.subject_type.
 *
 * Every value here must also be registered in the morph map declared by
 * AppServiceProvider, otherwise resolving the subject relation throws.
 */
final class ActivityLogSubject
{
    public const USER = 'user';

    public const APPOINTMENT = 'appointment';

    public const EXAMINATION = 'examination';

    public const PRESCRIPTION = 'prescription';

    public const PRESCRIPTION_ITEM = 'prescription_item';

    public const MEDICINE = 'medicine';

    public const INVOICE = 'invoice';

    public const PAYMENT = 'payment';
}
