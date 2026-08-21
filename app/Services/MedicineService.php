<?php

namespace App\Services;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Constants\MedicineMessage;
use App\Models\Medicine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handle medicine catalog queries and mutations.
 */
class MedicineService
{
    /**
     * Create a new medicine service instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return Medicine::query()
            ->search($filters['q'] ?? null)
            ->stockStatus($filters['stock_status'] ?? null)
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data): Medicine
    {
        return Medicine::query()->create($data);
    }

    public function load(Medicine $medicine): Medicine
    {
        return $medicine;
    }

    public function update(Medicine $medicine, array $data): Medicine
    {
        $medicine->update($data);

        return $medicine->refresh();
    }

    public function delete(Medicine $medicine): void
    {
        $medicine->delete();
    }

    public function adjustStock(Medicine $medicine, array $data): Medicine
    {
        return DB::transaction(function () use ($medicine, $data): Medicine {
            $lockedMedicine = Medicine::query()
                ->lockForUpdate()
                ->findOrFail($medicine->id);

            $newStock = $lockedMedicine->stock + $data['quantity'];

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => MedicineMessage::STOCK_CANNOT_BE_NEGATIVE,
                ]);
            }

            $stockBefore = (int) $lockedMedicine->stock;

            $lockedMedicine->update([
                'stock' => $newStock,
            ]);

            $this->logger->logChange(
                ActivityLogSubject::MEDICINE,
                (int) $lockedMedicine->getKey(),
                ActivityLogAction::STOCK_ADJUSTED,
                ['stock' => $stockBefore],
                ['stock' => (int) $newStock],
                ['quantity' => (int) $data['quantity']],
            );

            return $lockedMedicine->refresh();
        });
    }
}
