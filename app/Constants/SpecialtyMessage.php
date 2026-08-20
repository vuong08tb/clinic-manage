<?php

namespace App\Constants;

/**
 * Define response and validation messages for specialties.
 */
final class SpecialtyMessage
{
    public const LIST_RETRIEVED = 'Specialties retrieved';

    public const CREATED = 'Specialty created';

    public const RETRIEVED = 'Specialty retrieved';

    public const UPDATED = 'Specialty updated';

    public const DELETED = 'Specialty deleted';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const NAME_ALREADY_TAKEN = 'The specialty name has already been taken.';

    public const DESCRIPTION_TOO_LONG = 'The description may not be greater than 2000 characters.';

    public const UPDATE_FIELD_REQUIRED = 'At least one specialty field must be provided.';

    public const SPECIALTY_HAS_DOCTORS = 'This specialty cannot be deleted because it still has doctors assigned to it.';
}
