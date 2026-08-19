<?php

namespace App\Services;

use App\Constants\PrescriptionMessage;
use App\Models\Examination;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle prescription creation and stock-affecting business rules.
 */
class PrescriptionService
{
    /**
     * Create a prescription from an examination, deducting stock for any supplied items.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromExamination(array $data): Prescription
    {
        return DB::transaction(function () use ($data): Prescription {
            $examination = Examination::query()
                ->lockForUpdate()
                ->findOrFail($data['examination_id']);

            if (Prescription::query()
                ->where('examination_id', $examination->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'examination_id' => [PrescriptionMessage::EXAMINATION_ALREADY_HAS_PRESCRIPTION],
                ]);
            }

            $prescription = Prescription::query()->create([
                'examination_id' => $examination->getKey(),
                'doctor_id' => $examination->doctor_id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $this->deductStockAndCreateItem($prescription, $item);
            }

            return $prescription->load(['items.medicine', 'doctor.user']);
        });
    }

    /**
     * Lock the medicine, verify availability, deduct stock, and create the prescription item.
     *
     * Shared with future item-management endpoints so stock deduction stays consistent.
     *
     * @param  array<string, mixed>  $item
     */
    private function deductStockAndCreateItem(Prescription $prescription, array $item): PrescriptionItem
    {
        $medicine = Medicine::query()
            ->lockForUpdate()
            ->findOrFail($item['medicine_id']);

        if (! $medicine->is_active) {
            throw ValidationException::withMessages([
                'items' => [strtr(PrescriptionMessage::MEDICINE_NOT_ACTIVE, [':code' => $medicine->code])],
            ]);
        }

        if ($medicine->stock < $item['quantity']) {
            throw ValidationException::withMessages([
                'items' => [strtr(PrescriptionMessage::MEDICINE_INSUFFICIENT_STOCK, [':code' => $medicine->code])],
            ]);
        }

        $medicine->decrement('stock', $item['quantity']);

        return $prescription->items()->create([
            'medicine_id' => $medicine->getKey(),
            'quantity' => $item['quantity'],
            'dosage' => $item['dosage'],
            'usage_instruction' => $item['usage_instruction'] ?? null,
        ]);
    }
}
