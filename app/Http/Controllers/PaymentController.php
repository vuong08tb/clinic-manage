<?php

namespace App\Http\Controllers;

use App\Constants\PaymentMessage;
use App\Http\Requests\Payment\ListPaymentsRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PayPalService;
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
    public function __construct(
        private readonly PaymentService $service,
        private readonly PayPalService $payPalService,
    ) {}

    /**
     * Return filtered and paginated payments.
     */
    public function index(ListPaymentsRequest $request): JsonResponse
    {
        $payments = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            PaymentResource::collection($payments),
            PaymentMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
        );
    }

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

    /**
     * Capture a pending payment on PayPal and mark the invoice paid once fully settled.
     */
    public function capture(Payment $payment): JsonResponse
    {
        $captured = $this->service->capture($payment);

        return ApiResponse::resource(
            new PaymentResource($captured),
            match ($captured->status) {
                Payment::STATUS_COMPLETED => PaymentMessage::CAPTURED,
                Payment::STATUS_CANCELLED => PaymentMessage::CANCELLED,
                default => PaymentMessage::CAPTURE_FAILED,
            },
            Response::HTTP_OK,
        );
    }

    /**
     * Issue a browser-safe client token used to initialize the PayPal Web SDK
     * (Card Fields) on the frontend. client_secret never leaves the server.
     */
    public function clientToken(): JsonResponse
    {
        return ApiResponse::success(
            ['client_token' => $this->payPalService->generateClientToken()],
            PaymentMessage::CLIENT_TOKEN_RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
