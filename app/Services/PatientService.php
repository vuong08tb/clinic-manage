<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handle patient profile queries and mutations.
 */
class PatientService
{
    /**
     * Paginate active patient profiles with validated search filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Patient::query()
            ->search($filters['q'] ?? null)
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a patient and derive its stable public code from its primary key.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data): Patient {
            $patient = Patient::query()->create([
                ...$data,
                'code' => 'TMP-'.Str::random(16),
            ]);

            $patient->forceFill([
                'code' => sprintf('BN-%06d', (int) $patient->getKey()),
            ])->save();

            return $patient->refresh();
        });
    }

    /**
     * Return a patient profile for the detail resource.
     */
    public function load(Patient $patient): Patient
    {
        return $patient;
    }

    /**
     * Update a patient profile without changing its generated code.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Patient $patient, array $data): Patient
    {
        $patient->update($data);

        return $patient->refresh();
    }

    /**
     * Soft delete a patient profile.
     */
    public function delete(Patient $patient): void
    {
        $patient->delete();
    }
}
