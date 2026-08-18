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
     * Extract the customer-facing approval URL from a PayPal Order response.
     *
     * @param  array<string, mixed>  $order
     */
    private function extractApprovalUrl(array $order): ?string
    {
        return collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
    }
}
