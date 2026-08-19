<?php

namespace App\Http\Controllers;

use App\Constants\PrescriptionMessage;
use App\Http\Requests\Prescription\AddPrescriptionItemRequest;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\Prescription\UpdatePrescriptionItemRequest;
use App\Http\Resources\PrescriptionItemResource;
use App\Http\Resources\PrescriptionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
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

    /**
     * Add a medicine line to an existing prescription, deducting stock.
     */
    public function addItem(AddPrescriptionItemRequest $request, Prescription $prescription): JsonResponse
    {
        $item = $this->service->addItem($prescription, $request->validated());

        return ApiResponse::resource(
            new PrescriptionItemResource($item),
            PrescriptionMessage::ITEM_ADDED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a prescription item's quantity, dosage, or usage instruction.
     */
    public function updateItem(
        UpdatePrescriptionItemRequest $request,
        Prescription $prescription,
        PrescriptionItem $item,
    ): JsonResponse {
        $updatedItem = $this->service->updateItem($prescription, $item, $request->validated());

        return ApiResponse::resource(
            new PrescriptionItemResource($updatedItem),
            PrescriptionMessage::ITEM_UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Remove a prescription item and return its quantity to stock.
     */
    public function removeItem(Prescription $prescription, PrescriptionItem $item): JsonResponse
    {
        $this->service->removeItem($prescription, $item);

        return ApiResponse::success(
            null,
            PrescriptionMessage::ITEM_REMOVED,
            Response::HTTP_OK,
        );
    }
}
