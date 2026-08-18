<?php

namespace App\Http\Middleware;

use App\Constants\ExceptionMessage;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            throw new AuthenticationException(ExceptionMessage::UNAUTHENTICATED);
        }

        $routeAction = $request->route()?->getActionName();
        if ($routeAction === null || ! str_contains($routeAction, '@')) {
            throw new AccessDeniedHttpException(
                ExceptionMessage::ROUTE_PERMISSION_MAPPING_UNAVAILABLE,
            );
        }

        [$controllerClass, $method] = explode('@', $routeAction, 2);
        $controller = class_basename($controllerClass);
        $override = config("rbac.overrides.{$controller}@{$method}");

        if ($override !== null) {
            return $this->authorize($request, $next, $override);
        }

        $resource = config("rbac.controllers.{$controller}");

        if ($resource === null) {
            throw new AccessDeniedHttpException(sprintf(
                ExceptionMessage::CONTROLLER_PERMISSION_MAPPING_UNAVAILABLE,
                $controller,
            ));
        }

        $action = config("rbac.actions.{$method}", strtoupper($method));
        $permission = "{$resource}.{$action}";

        return $this->authorize($request, $next, $permission);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function authorize(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()->hasPermission($permission)) {
            throw new AccessDeniedHttpException(sprintf(
                ExceptionMessage::MISSING_PERMISSION,
                $permission,
            ));
        }

        return $next($request);
    }
}
