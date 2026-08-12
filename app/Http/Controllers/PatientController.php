<?php

namespace App\Http\Controllers;

use App\Constants\PatientMessage;
use App\Http\Requests\Patient\ListPatientsRequest;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected patient profile endpoints.
 */
class PatientController extends Controller
{
    /**
     * Create a new patient controller instance.
     */
    public function __construct(private readonly PatientService $service) {}

    /**
     * Return filtered and paginated patient profiles.
     */
    public function index(ListPatientsRequest $request): JsonResponse
    {
        $patients = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            PatientResource::collection($patients),
            PatientMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Create a patient profile.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->service->create($request->validated());

        return ApiResponse::resource(
            new PatientResource($patient),
            PatientMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return a patient profile.
     */
    public function show(Patient $patient): JsonResponse
    {
        return ApiResponse::resource(
            new PatientResource($this->service->load($patient)),
            PatientMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Update a patient profile.
     */
    public function update(
        UpdatePatientRequest $request,
        Patient $patient,
    ): JsonResponse {
        $updatedPatient = $this->service->update(
            $patient,
            $request->validated(),
        );

        return ApiResponse::resource(
            new PatientResource($updatedPatient),
            PatientMessage::UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Soft delete a patient profile.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $this->service->delete($patient);

        return ApiResponse::success(
            null,
            PatientMessage::DELETED,
            Response::HTTP_OK,
        );
    }
}
