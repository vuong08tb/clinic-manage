<?php

namespace App\Http\Controllers;

use App\Constants\RoleMessage;
use App\Http\Resources\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose the permission-protected, read-only role catalog.
 */
class RoleController extends Controller
{
    /**
     * Create a new role controller instance.
     */
    public function __construct(private readonly RoleService $service) {}

    /**
     * Return every seeded role.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::collection(
            RoleResource::collection($this->service->all()),
            RoleMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
