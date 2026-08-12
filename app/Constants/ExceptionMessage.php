<?php

namespace App\Constants;

/**
 * Define shared authentication and authorization exception messages.
 */
final class ExceptionMessage
{
    public const UNAUTHENTICATED = 'Unauthenticated.';

    public const ROUTE_PERMISSION_MAPPING_UNAVAILABLE = 'Permission mapping is not available for this route.';

    public const CONTROLLER_PERMISSION_MAPPING_UNAVAILABLE = 'Permission mapping is not available for %s.';

    public const MISSING_PERMISSION = 'Missing permission: %s';

    public const RESOURCE_NOT_FOUND = 'Resource not found.';

    public const METHOD_NOT_ALLOWED = 'Method not allowed.';

    public const REQUEST_FAILED = 'Request failed.';

    public const SERVER_ERROR = 'Server Error';
}
