<?php

namespace App\Http\Controllers;

use App\Constants\MedicineMessage;
use App\Http\Requests\Medicine\AdjustMedicineStockRequest;
use App\Http\Requests\Medicine\ListLowStockMedicinesRequest;
use App\Http\Requests\Medicine\ListMedicinesRequest;
use App\Http\Requests\Medicine\StoreMedicineRequest;
use App\Http\Requests\Medicine\UpdateMedicineRequest;
use App\Http\Resources\MedicineResource;
use App\Http\Responses\ApiResponse;
use App\Models\Medicine;
use App\Services\MedicineService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected medicine catalog endpoints.
 */
class MedicineController extends Controller
{
    /**
     * Create a new medicine controller instance.
     */
    public function __construct(private readonly MedicineService $service) {}

    /**
     * Return filtered and paginated medicines.
     */
    public function index(ListMedicinesRequest $request): JsonResponse
    {
        $medicines = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            MedicineResource::collection($medicines),
            MedicineMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Create a medicine.
     */
    public function store(StoreMedicineRequest $request): JsonResponse
    {
        $medicine = $this->service->create($request->validated());

        return ApiResponse::resource(
            new MedicineResource($medicine),
            MedicineMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return a medicine.
     */
    public function show(Medicine $medicine): JsonResponse
    {
        return ApiResponse::resource(
            new MedicineResource($this->service->load($medicine)),
            MedicineMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Update a medicine.
     */
    public function update(
        UpdateMedicineRequest $request,
        Medicine $medicine,
    ): JsonResponse {
        $updatedMedicine = $this->service->update(
            $medicine,
            $request->validated(),
        );

        return ApiResponse::resource(
            new MedicineResource($updatedMedicine),
            MedicineMessage::UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Soft delete a medicine.
     */
    public function destroy(Medicine $medicine): JsonResponse
    {
        $this->service->delete($medicine);

        return ApiResponse::success(
            null,
            MedicineMessage::DELETED,
            Response::HTTP_OK,
        );
    }

    /**
     * Adjust a medicine's stock quantity.
     */
    public function adjustStock(
        AdjustMedicineStockRequest $request,
        Medicine $medicine,
    ): JsonResponse {
        $adjustedMedicine = $this->service->adjustStock(
            $medicine,
            $request->validated(),
        );

        return ApiResponse::resource(
            new MedicineResource($adjustedMedicine),
            MedicineMessage::STOCK_ADJUSTED,
            Response::HTTP_OK,
        );
    }

    /**
     * List medicines that have fallen to or below the low-stock threshold.
     */
    public function lowStock(ListLowStockMedicinesRequest $request): JsonResponse
    {
        $medicines = $this->service->lowStock($request->validated());

        return ApiResponse::paginated(
            MedicineResource::collection($medicines),
            MedicineMessage::LOW_STOCK_RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
