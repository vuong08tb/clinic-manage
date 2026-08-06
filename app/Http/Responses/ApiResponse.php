<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Build the standard JSON envelope returned by API endpoints.
 */
final class ApiResponse
{
    /**
     * Return a successful response for raw, non-model payloads.
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return a successful response for a single API resource.
     */
    public static function resource(
        JsonResource $resource,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return self::success($resource->resolve(request()), $message, $status);
    }

    /**
     * Return a successful response for a non-paginated resource collection.
     */
    public static function collection(
        AnonymousResourceCollection $collection,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        return self::success($collection->resolve(request()), $message, $status);
    }

    /**
     * Return a paginated resource collection with project-specific metadata.
     */
    public static function paginated(
        AnonymousResourceCollection $collection,
        string $message = 'OK',
        int $status = 200,
    ): JsonResponse {
        $payload = $collection->response(request())->getData(true);
        $meta = Arr::only($payload['meta'] ?? [], [
            'current_page',
            'from',
            'last_page',
            'per_page',
            'to',
            'total',
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload['data'] ?? [],
            'meta' => $meta,
        ], $status);
    }

    /**
     * Return a failed API response with the supplied HTTP status.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function error(
        string $message,
        array $errors = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
