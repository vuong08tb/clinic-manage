<?php

namespace App\Http\Controllers;

use App\Constants\InvoiceMessage;
use App\Http\Requests\Invoice\ListInvoicesRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected invoice endpoints.
 */
class InvoiceController extends Controller
{
    /**
     * Create a new invoice controller instance.
     */
    public function __construct(private readonly InvoiceService $service) {}

    /**
     * Return filtered and paginated invoices.
     */
    public function index(ListInvoicesRequest $request): JsonResponse
    {
        $invoices = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            InvoiceResource::collection($invoices),
            InvoiceMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

    /**
     * Create an invoice from an examination, computing amounts from prescription items.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->service->createFromExamination($request->validated());

        return ApiResponse::resource(
            new InvoiceResource($invoice),
            InvoiceMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return an invoice with its examination and prescription context.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponse::resource(
            new InvoiceResource($this->service->load($invoice)),
            InvoiceMessage::RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
