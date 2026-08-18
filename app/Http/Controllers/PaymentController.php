<?php

namespace App\Http\Controllers;

use App\Constants\PaymentMessage;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose permission-protected payment endpoints.
 */
class PaymentController extends Controller
{
    /**
     * Create a new payment controller instance.
     */
    public function __construct(private readonly PaymentService $service) {}

    /**
     * Create a pending payment for an invoice by opening a PayPal Order.
     */
    public function store(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->service->create($invoice, $request->validated());

        return ApiResponse::resource(
            new PaymentResource($payment),
            PaymentMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }
}
