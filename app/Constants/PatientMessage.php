<?php

namespace App\Constants;

/**
 * Define response and validation messages for patients.
 */
final class PatientMessage
{
    public const LIST_RETRIEVED = 'Patients retrieved';

    public const CREATED = 'Patient created';

    public const RETRIEVED = 'Patient retrieved';

    public const UPDATED = 'Patient updated';

    public const DELETED = 'Patient deleted';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const CODE_GENERATED_AUTOMATICALLY = 'The patient code is generated automatically.';

    public const CODE_CANNOT_BE_CHANGED = 'The patient code cannot be changed.';

    public const INVALID_GENDER = 'The gender must be male, female, or other.';

    public const DATE_OF_BIRTH_IN_FUTURE = 'The date of birth may not be in the future.';

    public const EMAIL_ALREADY_TAKEN = 'The email has already been taken.';

    public const ADDRESS_TOO_LONG = 'The address may not be greater than 255 characters.';

    public const UPDATE_FIELD_REQUIRED = 'At least one patient field must be provided.';
}
