<?php

namespace App\Http\Controllers;

use App\Constants\AppointmentMessage;
use App\Http\Requests\Appointment\ListAppointmentsRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
            AppointmentMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
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
            AppointmentMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return an appointment with its clinical context.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        return ApiResponse::resource(
            new AppointmentResource($this->service->load($appointment)),
            AppointmentMessage::RETRIEVED,
            Response::HTTP_OK,
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
            AppointmentMessage::UPDATED,
            Response::HTTP_OK,
        );
    }

    /**
     * Transition an appointment to an allowed lifecycle status.
     */
    public function updateStatus(
        UpdateAppointmentStatusRequest $request,
        Appointment $appointment,
    ): JsonResponse {
        $updatedAppointment = $this->service->updateStatus(
            $appointment,
            $request->validated('status'),
        );

        return ApiResponse::resource(
            new AppointmentResource($updatedAppointment),
            AppointmentMessage::STATUS_UPDATED,
            Response::HTTP_OK,
        );
    }
}
