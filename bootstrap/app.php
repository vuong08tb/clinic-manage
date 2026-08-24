<?php

use App\Constants\ExceptionMessage;
use App\Http\Middleware\EnsurePermission;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Failures Laravel drops from the log by default. Every one of them is a client mistake that the
 * API still answers with a 4xx, so leaving them ignored means a rejected request leaves no trace
 * anywhere: only uncaught server faults ever reach the log.
 */
$clientErrors = [
    ValidationException::class,
    AuthenticationException::class,
    AuthorizationException::class,
    ModelNotFoundException::class,
    HttpException::class,
];

/**
 * Reporting runs before rendering, so a logged status has to be derived from the exception rather
 * than read off a response. The arms mirror the render callbacks below, which is what keeps the
 * logged status honest about what the client actually received.
 */
$statusFor = static fn (Throwable $exception): int => match (true) {
    $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
    $exception instanceof ValidationException => $exception->status,
    $exception instanceof AuthenticationException => HttpResponse::HTTP_UNAUTHORIZED,
    $exception instanceof AuthorizationException => HttpResponse::HTTP_FORBIDDEN,
    $exception instanceof ModelNotFoundException => HttpResponse::HTTP_NOT_FOUND,
    default => HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
};

/**
 * Identifies the request behind a log entry. A console run has no request worth describing, but
 * the test runner also reports as console while dispatching real requests, so it is kept out of
 * that shortcut. LARAVEL_START is defined by the HTTP and console entry points, not by PHPUnit.
 *
 * @return array<string, mixed>
 */
$requestContext = static function (): array {
    if (app()->runningInConsole() && ! app()->runningUnitTests()) {
        return [];
    }

    $context = [
        'method' => request()->method(),
        'url' => request()->fullUrl(),
        'user_id' => Auth::id(),
        'ip' => request()->ip(),
    ];

    if (defined('LARAVEL_START')) {
        $context['duration_ms'] = round((microtime(true) - LARAVEL_START) * 1000);
    }

    return $context;
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API authentication failures must return JSON instead of redirecting to a web login route.
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') ? null : '/login',
        );

        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($clientErrors, $requestContext, $statusFor): void {
        // Route client failures into the report callback below instead of dropping them.
        $exceptions->stopIgnoring($clientErrors);

        // The default reporter writes an exception message and nothing about the call that
        // produced it. This attaches the request to every entry it writes, server faults included.
        $exceptions->context(fn (Throwable $exception): array => $requestContext());

        // A client error is expected traffic, not an incident: write one compact warning line and
        // return false to suppress the default report, which would otherwise attach a full stack
        // trace to every failed validation. Anything 5xx - including an abort(503), which is an
        // HttpException the list above just un-ignored - falls through to the default reporter and
        // keeps its trace at error level.
        $exceptions->report(function (Throwable $exception) use ($requestContext, $statusFor): bool {
            $status = $statusFor($exception);

            if ($status >= HttpResponse::HTTP_INTERNAL_SERVER_ERROR) {
                return true;
            }

            $context = $requestContext() + [
                'status' => $status,
                'type' => class_basename($exception),
            ];

            if ($exception instanceof ValidationException) {
                $context['errors'] = $exception->errors();
            }

            Log::warning($exception->getMessage() ?: class_basename($exception), $context);

            return false;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Preserve field-level validation errors inside the standard API envelope.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                $exception->errors(),
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        });

        // Normalize failures from both login and the Sanctum authentication guard.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                status: HttpResponse::HTTP_UNAUTHORIZED,
            );
        });

        // Return one forbidden envelope for policy and permission middleware failures.
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                $exception->getMessage(),
                status: HttpResponse::HTTP_FORBIDDEN,
            );
        });

        // Hide model identifiers and framework details from not-found responses.
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ExceptionMessage::RESOURCE_NOT_FOUND,
                status: HttpResponse::HTTP_NOT_FOUND,
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                ExceptionMessage::METHOD_NOT_ALLOWED,
                status: HttpResponse::HTTP_METHOD_NOT_ALLOWED,
            );
        });

        // Normalize any remaining HTTP exception without changing its status code.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage()
                ?: (HttpResponse::$statusTexts[$status] ?? ExceptionMessage::REQUEST_FAILED);

            return ApiResponse::error($message, status: $status);
        });

        // API failures must never render an HTML exception page.
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = config('app.debug')
                ? $exception->getMessage()
                : ExceptionMessage::SERVER_ERROR;

            return ApiResponse::error(
                $message ?: ExceptionMessage::SERVER_ERROR,
                status: HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
