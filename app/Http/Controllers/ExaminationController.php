<?php

namespace App\Http\Controllers;

use App\Constants\ExaminationMessage;
use App\Http\Requests\Examination\ListExaminationsRequest;
use App\Http\Requests\Examination\StoreExaminationRequest;
use App\Http\Requests\Examination\UpdateExaminationRequest;
use App\Http\Resources\ExaminationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Examination;
use App\Services\ExaminationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected examination endpoints.
 */
class ExaminationController extends Controller
{
    /**
     * Create a new examination controller instance.
     */
    public function __construct(private readonly ExaminationService $service) {}

    /**
     * Return filtered and paginated examinations.
     */
    public function index(ListExaminationsRequest $request): JsonResponse
    {
        $examinations = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            ExaminationResource::collection($examinations),
            ExaminationMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
        
    }

    /**
     * Create an examination from a confirmed appointment.
     */
    public function store(StoreExaminationRequest $request): JsonResponse
    {
        $examination = $this->service->createFromAppointment($request->validated());

        return ApiResponse::resource(
            new ExaminationResource($examination),
            ExaminationMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return an examination with its clinical context.
     */
    public function show(Examination $examination): JsonResponse
    {
        return ApiResponse::resource(
            new ExaminationResource($this->service->load($examination)),
            ExaminationMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Update the mutable clinical fields of an examination.
     */
    public function update(
        UpdateExaminationRequest $request,
        Examination $examination,
    ): JsonResponse {
        $updatedExamination = $this->service->update(
            $examination,
            $request->validated(),
        );

        return ApiResponse::resource(
            new ExaminationResource($updatedExamination),
            ExaminationMessage::UPDATED,
            Response::HTTP_OK,
        );
    }
}