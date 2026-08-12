<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for doctors.
 */
final class DoctorMessage
{
    public const LIST_RETRIEVED = 'Doctors retrieved';

    public const CREATED = 'Doctor created';

    public const RETRIEVED = 'Doctor retrieved';

    public const UPDATED = 'Doctor updated';

    public const DELETED = 'Doctor deleted';

    public const SELECTED_USER_NOT_FOUND = 'The selected user does not exist.';

    public const USER_ALREADY_HAS_PROFILE = 'The selected user already has a doctor profile.';

    public const SELECTED_SPECIALTY_NOT_FOUND = 'The selected specialty does not exist.';

    public const LICENSE_NUMBER_ALREADY_TAKEN = 'The license number has already been taken.';

    public const BIO_TOO_LONG = 'The bio may not be greater than 5000 characters.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const UPDATE_FIELD_REQUIRED = 'At least one doctor field must be provided.';

    public const USER_MUST_HAVE_DOCTOR_ROLE = 'The selected user must have the DOCTOR role.';
}
