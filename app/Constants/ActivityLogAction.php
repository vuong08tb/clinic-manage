<?php

namespace App\Constants;

/**
 * Define the action names recorded in activity_logs.action.
 *
 * Stock and capture actions are recorded explicitly by their service because the
 * business context they carry is not available from a model event alone.
 */
final class ActivityLogAction
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const STATUS_CHANGED = 'status_changed';

    public const STOCK_DEDUCTED = 'stock_deducted';

    public const STOCK_RESTORED = 'stock_restored';

    public const STOCK_ADJUSTED = 'stock_adjusted';

    public const CAPTURED = 'captured';

    public const CAPTURE_FAILED = 'capture_failed';
}
