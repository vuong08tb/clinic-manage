<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for examinations.
 */
final class ExaminationMessage
{
    public const LIST_RETRIEVED = 'Examinations retrieved';

    public const CREATED = 'Examination created';

    public const RETRIEVED = 'Examination retrieved';

    public const UPDATED = 'Examination updated';

    public const SELECTED_DOCTOR_NOT_FOUND = 'The selected doctor does not exist.';

    public const SELECTED_PATIENT_NOT_FOUND = 'The selected patient does not exist.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const SELECTED_APPOINTMENT_NOT_FOUND = 'The selected appointment does not exist.';

    public const PATIENT_ASSIGNED_FROM_APPOINTMENT = 'The examination patient is assigned from the appointment.';

    public const DOCTOR_ASSIGNED_FROM_APPOINTMENT = 'The examination doctor is assigned from the appointment.';

    public const TIME_ASSIGNED_AUTOMATICALLY = 'The examination time is assigned automatically.';

    public const UPDATE_FIELD_REQUIRED = 'At least one examination field must be provided.';

    public const APPOINTMENT_CANNOT_BE_CHANGED = 'The examination appointment cannot be changed.';

    public const PATIENT_CANNOT_BE_CHANGED = 'The examination patient cannot be changed.';

    public const DOCTOR_CANNOT_BE_CHANGED = 'The examination doctor cannot be changed.';

    public const TIME_CANNOT_BE_CHANGED = 'The examination time cannot be changed.';

    public const APPOINTMENT_ALREADY_EXAMINED = 'The appointment already has an examination.';

    public const ONLY_CONFIRMED_APPOINTMENTS = 'Only confirmed appointments may be examined.';
}
