<?php

namespace App\Services;

use App\Constants\PrescriptionMessage;
use App\Models\Examination;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Handle prescription creation and stock-affecting business rules.
 */
class PrescriptionService
{
        /**
     * Paginate prescriptions with validated filters and eager-loaded clinical context.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Prescription::query()
            ->with(['items.medicine', 'doctor.user', 'examination.patient'])
            ->when(
                isset($filters['examination_id']),
                fn ($query) => $query->where('examination_id', $filters['examination_id']),
            )
            ->when(
                isset($filters['doctor_id']),
                fn ($query) => $query->where('doctor_id', $filters['doctor_id']),
            )
            ->when(
                isset($filters['patient_id']),
                fn ($query) => $query->whereHas(
                    'examination',
                    fn ($examinationQuery) => $examinationQuery->where('patient_id', $filters['patient_id']),
                ),
            )
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Load the relationships required by the prescription resource.
     */
    public function load(Prescription $prescription): Prescription
    {
        return $prescription->loadMissing(['items.medicine', 'doctor.user', 'examination.patient']);
    }

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
     * Add a medicine line to an existing prescription, deducting stock in a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(Prescription $prescription, array $data): PrescriptionItem
    {
        return DB::transaction(
            fn (): PrescriptionItem => $this->deductStockAndCreateItem($prescription, $data)->load('medicine'),
        );
    }

    /**
     * Update a prescription item's quantity, dosage, or usage instruction, adjusting stock by
     * the quantity delta.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(Prescription $prescription, PrescriptionItem $item, array $data): PrescriptionItem
    {
        return DB::transaction(function () use ($prescription, $item, $data): PrescriptionItem {
            $this->assertItemBelongsToPrescription($item, $prescription);

            $lockedItem = PrescriptionItem::query()
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            if (array_key_exists('quantity', $data)) {
                $medicine = Medicine::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedItem->medicine_id);

                $delta = $data['quantity'] - $lockedItem->quantity;

                if ($delta > 0) {
                    if (! $medicine->is_active) {
                        throw ValidationException::withMessages([
                            'quantity' => [strtr(PrescriptionMessage::MEDICINE_NOT_ACTIVE, [':code' => $medicine->code])],
                        ]);
                    }

                    if ($medicine->stock < $delta) {
                        throw ValidationException::withMessages([
                            'quantity' => [strtr(PrescriptionMessage::MEDICINE_INSUFFICIENT_STOCK, [':code' => $medicine->code])],
                        ]);
                    }

                    $medicine->decrement('stock', $delta);
                } elseif ($delta < 0) {
                    $medicine->increment('stock', abs($delta));
                }
            }

            $lockedItem->update(Arr::only($data, ['quantity', 'dosage', 'usage_instruction']));

            return $lockedItem->refresh()->load('medicine');
        });
    }

    /**
     * Remove a prescription item and return its quantity to stock.
     */
    public function removeItem(Prescription $prescription, PrescriptionItem $item): void
    {
        DB::transaction(function () use ($prescription, $item): void {
            $this->assertItemBelongsToPrescription($item, $prescription);

            $lockedItem = PrescriptionItem::query()
                ->lockForUpdate()
                ->findOrFail($item->getKey());

            $medicine = Medicine::query()
                ->lockForUpdate()
                ->findOrFail($lockedItem->medicine_id);

            $medicine->increment('stock', $lockedItem->quantity);
            $lockedItem->delete();
        });
    }

    /**
     * Lock the medicine, verify availability, deduct stock, and create the prescription item.
     *
     * Shared with item-management endpoints so stock deduction stays consistent.
     *
     * @param  array<string, mixed>  $item
     */
    private function deductStockAndCreateItem(Prescription $prescription, array $item): PrescriptionItem
    {
        $medicine = Medicine::query()
            ->lockForUpdate()
            ->findOrFail($item['medicine_id']);

        if (PrescriptionItem::query()
            ->where('prescription_id', $prescription->getKey())
            ->where('medicine_id', $medicine->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'medicine_id' => [strtr(PrescriptionMessage::MEDICINE_ALREADY_IN_PRESCRIPTION, [':code' => $medicine->code])],
            ]);
        }

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

    /**
     * Reject item mutations where the item does not belong to the given prescription.
     */
    private function assertItemBelongsToPrescription(PrescriptionItem $item, Prescription $prescription): void
    {
        if ($item->prescription_id !== $prescription->getKey()) {
            throw new ModelNotFoundException;
        }
    }
}