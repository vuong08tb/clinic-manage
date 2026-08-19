<?php

namespace App\Http\Controllers;

use App\Http\Requests\Appointment\ListAppointmentsRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

/**
 * Expose permission-protected appointment endpoints.
 */
class AppointmentController extends Controller
{
    /**
     * Create a new appointment controller instance.
     */
    public function __construct(private readonly AppointmentService $service) {}

    /**
     * Return filtered and paginated appointments.
     */
    public function index(ListAppointmentsRequest $request): JsonResponse
    {
        $appointments = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            AppointmentResource::collection($appointments),
            'Appointments retrieved',
        );
    }

    /**
     * Create a scheduled appointment.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->service->create($request->validated());

        return ApiResponse::resource(
            new AppointmentResource($appointment),
            'Appointment created',
            201,
        );
    }

    /**
     * Return an appointment with its clinical context.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        return ApiResponse::resource(
            new AppointmentResource($this->service->load($appointment)),
            'Appointment retrieved',
        );
    }

    /**
     * Update a scheduled appointment.
     */
    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
    ): JsonResponse {
        $updatedAppointment = $this->service->update(
            $appointment,
            $request->validated(),
        );

        return ApiResponse::resource(
            new AppointmentResource($updatedAppointment),
            'Appointment updated',
        );
    }
}
