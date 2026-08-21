<?php

namespace App\Services;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Constants\PaymentMessage;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle payment creation rules and PayPal Order orchestration.
 */
class PaymentService
{
    /**
     * Create a new payment service instance.
     */
    public function __construct(
        private readonly PayPalService $payPalService,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Paginate payments with validated filters, most recent first.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Payment::query()
            ->when(
                isset($filters['invoice_id']),
                fn ($query) => $query->where('invoice_id', $filters['invoice_id']),
            )
            ->when(
                isset($filters['provider_order_id']),
                fn ($query) => $query->where('provider_order_id', $filters['provider_order_id']),
            )
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a pending payment for an unpaid invoice by opening a PayPal Order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data): Payment {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($lockedInvoice->status !== Invoice::STATUS_UNPAID) {
                throw ValidationException::withMessages([
                    'invoice' => [PaymentMessage::INVOICE_NOT_PAYABLE],
                ]);
            }

            $completedTotal = (float) $lockedInvoice->payments()
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');
            $remaining = (float) $lockedInvoice->total - $completedTotal;

            if ((float) $data['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => [strtr(PaymentMessage::AMOUNT_EXCEEDS_REMAINING, [
                        ':remaining' => number_format($remaining, 2, '.', ''),
                    ])],
                ]);
            }

            $order = $this->payPalService->createOrder((float) $data['amount']);

            $payment = $lockedInvoice->payments()->create([
                'amount' => $data['amount'],
                'method' => $data['method'],
                'status' => Payment::STATUS_PENDING,
                'provider' => 'paypal',
                'provider_order_id' => $order['id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $payment->setAttribute('approval_url', $this->extractApprovalUrl($order));

            return $payment;
        });
    }

    /**
     * Capture a pending payment on PayPal and reconcile the invoice status.
     */
    public function capture(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            // Capture is retried by design: PayPal can redirect the customer back more
            // than once, and the browser back button replays the return page. Report the
            // settled payment instead of failing a request whose outcome already happened.
            if ($lockedPayment->status === Payment::STATUS_COMPLETED) {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== Payment::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'payment' => [PaymentMessage::PAYMENT_CANNOT_BE_CAPTURED],
                ]);
            }

            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($lockedPayment->invoice_id);

            $completedTotal = (float) $lockedInvoice->payments()
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');

            // Reject before touching PayPal: capturing real money we would then have to
            // discard as "failed" locally is worse than blocking the request up front.
            if ($completedTotal + (float) $lockedPayment->amount > (float) $lockedInvoice->total) {
                throw ValidationException::withMessages([
                    'payment' => [PaymentMessage::CAPTURE_WOULD_EXCEED_TOTAL],
                ]);
            }

            $order = $this->payPalService->captureOrder($lockedPayment->provider_order_id);
            $success = data_get($order, 'status') === 'COMPLETED';
            $statusBefore = $lockedPayment->status;

            if ($success) {
                $lockedPayment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'provider_capture_id' => data_get($order, 'purchase_units.0.payments.captures.0.id'),
                    'paid_at' => now(),
                ]);

                $this->logger->logChange(
                    ActivityLogSubject::PAYMENT,
                    (int) $lockedPayment->getKey(),
                    ActivityLogAction::CAPTURED,
                    ['status' => $statusBefore],
                    ['status' => Payment::STATUS_COMPLETED],
                    ['provider_capture_id' => $lockedPayment->provider_capture_id],
                );

                if ($completedTotal + (float) $lockedPayment->amount >= (float) $lockedInvoice->total) {
                    $lockedInvoice->update(['status' => Invoice::STATUS_PAID]);
                }
            } else {
                $lockedPayment->update(['status' => Payment::STATUS_FAILED]);

                $this->logger->logChange(
                    ActivityLogSubject::PAYMENT,
                    (int) $lockedPayment->getKey(),
                    ActivityLogAction::CAPTURE_FAILED,
                    ['status' => $statusBefore],
                    ['status' => Payment::STATUS_FAILED],
                );
            }

            return $lockedPayment->refresh();
        });
    }

    /**
     * Extract the customer-facing approval URL from a PayPal Order response.
     *
     * @param  array<string, mixed>  $order
     */
    private function extractApprovalUrl(array $order): ?string
    {
        return collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
    }
}
