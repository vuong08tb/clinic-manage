<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Provide the standard JSON response envelope used by API controllers.
 */
trait ApiResponse
{
    /**
     * Return a successful API response.
     */
    protected function ok(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Return a failed API response.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function fail(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
