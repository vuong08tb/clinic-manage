<?php

namespace App\Constants;

/**
 * Define response, validation, and business messages for users.
 */
final class UserMessage
{
    public const LIST_RETRIEVED = 'Users retrieved';

    public const CREATED = 'User created';

    public const RETRIEVED = 'User retrieved';

    public const UPDATED = 'User updated';

    public const DEACTIVATED = 'User deactivated';

    public const STATUS_UPDATED = 'User status updated';

    public const SELECTED_ROLE_NOT_FOUND = 'The selected role does not exist.';

    public const ACTIVE_STATUS_MUST_BE_BOOLEAN = 'The active status must be true or false.';

    public const ACTIVE_STATUS_REQUIRED = 'The active status is required.';

    public const PAGE_SIZE_TOO_LARGE = 'The page size may not be greater than 100.';

    public const EMAIL_ALREADY_TAKEN = 'The email has already been taken.';

    public const PASSWORD_CONFIRMATION_MISMATCH = 'The password confirmation does not match.';

    public const USE_STATUS_ENDPOINT = 'Use the user status endpoint to change the active status.';

    public const UPDATE_FIELD_REQUIRED = 'At least one user field must be provided.';

    public const ADMIN_ROLE_NOT_CONFIGURED = 'The ADMIN role is not configured.';

    public const LAST_ACTIVE_ADMIN_ROLE_CHANGE = 'The last active administrator cannot be assigned another role.';

    public const LAST_ACTIVE_ADMIN_DEACTIVATION = 'The last active administrator cannot be deactivated.';
}
