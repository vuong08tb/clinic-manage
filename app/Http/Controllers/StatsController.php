<?php

namespace App\Http\Controllers;

use App\Constants\StatsMessage;
use App\Http\Resources\StatsResource;
use App\Http\Responses\ApiResponse;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose the permission-protected clinic overview statistics.
 */
class StatsController extends Controller
{
    /**
     * Create a new stats controller instance.
     */
    public function __construct(private readonly StatsService $service) {}

    /**
     * Return the clinic overview figures.
     */
    public function show(): JsonResponse
    {
        return ApiResponse::resource(
            new StatsResource($this->service->overview()),
            StatsMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
