<?php

namespace App\Http\Controllers;

use App\Constants\SpecialtyMessage;
use App\Http\Requests\Specialty\ListSpecialtiesRequest;
use App\Http\Requests\Specialty\StoreSpecialtyRequest;
use App\Http\Requests\Specialty\UpdateSpecialtyRequest;
use App\Http\Resources\SpecialtyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Specialty;
use App\Services\SpecialtyService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected specialty catalog endpoints.
 */
class SpecialtyController extends Controller
{
    /**
     * Create a new specialty controller instance.
     */
    public function __construct(private readonly SpecialtyService $service) {}

    /**
     * Return a filtered and paginated specialty catalog.
     */
    public function index(ListSpecialtiesRequest $request): JsonResponse
    {
        $specialties = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            SpecialtyResource::collection($specialties),
            SpecialtyMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Create a specialty.
     */
    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        $specialty = $this->service->create($request->validated());

        return ApiResponse::resource(
            new SpecialtyResource($specialty),
            SpecialtyMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return a specialty.
     */
    public function show(Specialty $specialty): JsonResponse
    {
        return ApiResponse::resource(
            new SpecialtyResource($this->service->load($specialty)),
            SpecialtyMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Update a specialty.
     */
    public function update(
        UpdateSpecialtyRequest $request,
        Specialty $specialty,
    ): JsonResponse {
        $updatedSpecialty = $this->service->update(
            $specialty,
            $request->validated(),
        );

        return ApiResponse::resource(
            new SpecialtyResource($updatedSpecialty),
            SpecialtyMessage::UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Delete a specialty.
     */
    public function destroy(Specialty $specialty): JsonResponse
    {
        $this->service->delete($specialty);

        return ApiResponse::success(
            null,
            SpecialtyMessage::DELETED,
            Response::HTTP_OK,
        );
    }
}
