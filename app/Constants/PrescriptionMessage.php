<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for prescriptions.
 */
final class PrescriptionMessage
{
    public const CREATED = 'Prescription created';

    public const UPDATED = 'Prescription updated';

    public const UPDATE_FIELD_REQUIRED = 'At least one prescription field must be provided.';

    public const EXAMINATION_CANNOT_BE_CHANGED = 'The prescription examination cannot be changed.';

    public const ITEMS_MANAGED_SEPARATELY = 'Prescription items are managed through the item endpoints.';

    public const SELECTED_EXAMINATION_NOT_FOUND = 'The selected examination does not exist.';

    public const DOCTOR_ASSIGNED_FROM_EXAMINATION = 'The prescription doctor is assigned from the examination.';

    public const EXAMINATION_ALREADY_HAS_PRESCRIPTION = 'The examination already has a prescription.';

    public const MEDICINE_NOT_ACTIVE = 'Medicine :code is not active.';

    public const MEDICINE_INSUFFICIENT_STOCK = 'Medicine :code has insufficient stock.';

    public const ITEM_ADDED = 'Prescription item added';

    public const ITEM_UPDATED = 'Prescription item updated';

    public const ITEM_REMOVED = 'Prescription item removed';

    public const MEDICINE_ALREADY_IN_PRESCRIPTION = 'Medicine :code is already in this prescription. Use updateItem to change the quantity.';

    public const ITEM_UPDATE_FIELD_REQUIRED = 'At least one prescription item field must be provided.';

    public const ITEM_MEDICINE_CANNOT_BE_CHANGED = 'The prescription item medicine cannot be changed.';

    public const LIST_RETRIEVED = 'Prescriptions retrieved';

    public const RETRIEVED = 'Prescription retrieved';

    public const SELECTED_DOCTOR_NOT_FOUND = 'The selected doctor does not exist.';

    public const SELECTED_PATIENT_NOT_FOUND = 'The selected patient does not exist.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';
}
