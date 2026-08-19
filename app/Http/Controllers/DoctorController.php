<?php

namespace App\Http\Controllers;

use App\Constants\DoctorMessage;
use App\Http\Requests\Doctor\ListDoctorsRequest;
use App\Http\Requests\Doctor\StoreDoctorRequest;
use App\Http\Requests\Doctor\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Services\DoctorService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected doctor profile endpoints.
 */
class DoctorController extends Controller
{
    /**
     * Create a new doctor controller instance.
     */
    public function __construct(private readonly DoctorService $service) {}

    /**
     * Return filtered and paginated doctor profiles.
     */
    public function index(ListDoctorsRequest $request): JsonResponse
    {
        $doctors = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            DoctorResource::collection($doctors),
            DoctorMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Create a doctor profile.
     */
    public function store(StoreDoctorRequest $request): JsonResponse
    {
        $doctor = $this->service->create($request->validated());

        return ApiResponse::resource(
            new DoctorResource($doctor),
            DoctorMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return a doctor profile.
     */
    public function show(Doctor $doctor): JsonResponse
    {
        return ApiResponse::resource(
            new DoctorResource($this->service->load($doctor)),
            DoctorMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Update a doctor profile.
     */
    public function update(
        UpdateDoctorRequest $request,
        Doctor $doctor,
    ): JsonResponse {
        $updatedDoctor = $this->service->update(
            $doctor,
            $request->validated(),
        );

        return ApiResponse::resource(
            new DoctorResource($updatedDoctor),
            DoctorMessage::UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Delete a doctor profile.
     */
    public function destroy(Doctor $doctor): JsonResponse
    {
        $this->service->delete($doctor);

        return ApiResponse::success(
            null,
            DoctorMessage::DELETED,
            Response::HTTP_OK,
        );
    }
}
