<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for prescriptions.
 */
final class PrescriptionMessage
{
    public const CREATED = 'Prescription created';

    public const SELECTED_EXAMINATION_NOT_FOUND = 'The selected examination does not exist.';

    public const DOCTOR_ASSIGNED_FROM_EXAMINATION = 'The prescription doctor is assigned from the examination.';

    public const EXAMINATION_ALREADY_HAS_PRESCRIPTION = 'The examination already has a prescription.';

    public const MEDICINE_NOT_ACTIVE = 'Medicine :code is not active.';

    public const MEDICINE_INSUFFICIENT_STOCK = 'Medicine :code has insufficient stock.';
}
