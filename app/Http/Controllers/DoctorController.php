<?php

namespace App\Http\Controllers;

use App\Http\Requests\Doctor\ListDoctorsRequest;
use App\Http\Requests\Doctor\StoreDoctorRequest;
use App\Http\Requests\Doctor\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Services\DoctorService;
use Illuminate\Http\JsonResponse;

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
            'Doctors retrieved',
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
            'Doctor created',
            201,
        );
    }

    /**
     * Return a doctor profile.
     */
    public function show(Doctor $doctor): JsonResponse
    {
        return ApiResponse::resource(
            new DoctorResource($this->service->load($doctor)),
            'Doctor retrieved',
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
            'Doctor updated',
        );
    }

    /**
     * Delete a doctor profile.
     */
    public function destroy(Doctor $doctor): JsonResponse
    {
        $this->service->delete($doctor);

        return ApiResponse::success(null, 'Doctor deleted');
    }
}
