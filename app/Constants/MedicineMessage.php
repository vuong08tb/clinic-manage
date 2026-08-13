<?php

namespace App\Constants;

/**
 * Define response and validation messages for medicines.
 */
final class MedicineMessage
{
    public const LIST_RETRIEVED = 'Medicines retrieved';

    public const CREATED = 'Medicine created';

    public const RETRIEVED = 'Medicine retrieved';

    public const UPDATED = 'Medicine updated';

    public const DELETED = 'Medicine deleted';

    public const STOCK_ADJUSTED = 'Medicine stock adjusted';

    public const STOCK_CANNOT_BE_NEGATIVE = 'The resulting stock cannot be negative.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const CODE_ALREADY_TAKEN = 'The code has already been taken.';

    public const INVALID_STOCK_STATUS = 'The stock status must be in_stock or out_of_stock.';

    public const UPDATE_FIELD_REQUIRED = 'At least one medicine field must be provided.';
}
