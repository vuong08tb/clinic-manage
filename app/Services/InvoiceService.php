<?php

namespace App\Services;

use App\Constants\InvoiceMessage;
use App\Models\Examination;
use App\Models\Invoice;
use App\Models\PrescriptionItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handle invoice queries and billing calculation rules.
 */
class InvoiceService
{
    /**
     * Paginate invoices with validated filters and eager-loaded examination context.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['examination.patient', 'examination.doctor.user'])
            ->when(
                isset($filters['status']),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                isset($filters['examination_id']),
                fn ($query) => $query->where('examination_id', $filters['examination_id']),
            )
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Load the relationships required by the invoice resource.
     */
    public function load(Invoice $invoice): Invoice
    {
        return $invoice->loadMissing([
            'examination.patient',
            'examination.doctor.user',
            'examination.prescription.items.medicine',
        ]);
    }

    /**
     * Create an invoice from an examination, computing subtotal/total from prescription items
     * and the configured examination fee.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromExamination(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $examination = Examination::query()
                ->lockForUpdate()
                ->with('prescription.items.medicine')
                ->findOrFail($data['examination_id']);

            if (Invoice::query()
                ->where('examination_id', $examination->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'examination' => [InvoiceMessage::EXAMINATION_ALREADY_INVOICED],
                ]);
            }

            $medicineTotal = $examination->prescription?->items
                ->sum(fn (PrescriptionItem $item): float => $item->quantity * (float) $item->medicine->price) ?? 0.0;

            $subtotal = $medicineTotal + (float) config('clinic.examination_fee');
            $discount = (float) ($data['discount'] ?? 0);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount' => [InvoiceMessage::DISCOUNT_EXCEEDS_SUBTOTAL],
                ]);
            }

            $invoice = Invoice::query()->create([
                'examination_id' => $examination->getKey(),
                'invoice_code' => 'TMP-'.Str::random(16),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
            ]);

            $invoice->forceFill([
                'invoice_code' => sprintf('INV-%06d', (int) $invoice->getKey()),
            ])->save();

            return $invoice->refresh()->load([
                'examination.patient',
                'examination.doctor.user',
                'examination.prescription.items.medicine',
            ]);
        });
    }
}
