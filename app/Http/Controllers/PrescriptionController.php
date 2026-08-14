<?php

namespace App\Http\Controllers;

use App\Constants\PrescriptionMessage;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Http\Responses\ApiResponse;
use App\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected prescription endpoints.
 */
class PrescriptionController extends Controller
{
    /**
     * Create a new prescription controller instance.
     */
    public function __construct(private readonly PrescriptionService $service) {}

    /**
     * Create a prescription from an examination, deducting stock for any supplied items.
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $prescription = $this->service->createFromExamination($request->validated());

        return ApiResponse::resource(
            new PrescriptionResource($prescription),
            PrescriptionMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }
}
