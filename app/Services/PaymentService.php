<?php

namespace App\Services;

use App\Constants\PaymentMessage;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle payment creation rules and PayPal Order orchestration.
 */
class PaymentService
{
    public function __construct(private readonly PayPalService $payPalService) {}

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

            if ($success) {
                $lockedPayment->update([
                    'status' => Payment::STATUS_COMPLETED,
                    'provider_capture_id' => data_get($order, 'purchase_units.0.payments.captures.0.id'),
                    'paid_at' => now(),
                ]);

                if ($completedTotal + (float) $lockedPayment->amount >= (float) $lockedInvoice->total) {
                    $lockedInvoice->update(['status' => Invoice::STATUS_PAID]);
                }
            } else {
                $lockedPayment->update(['status' => Payment::STATUS_FAILED]);
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
