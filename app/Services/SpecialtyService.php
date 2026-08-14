<?php

namespace App\Services;

use App\Models\Specialty;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Handle specialty catalog queries and mutations.
 */
class SpecialtyService
{
    /**
     * Paginate specialties with validated catalog filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Specialty::query();

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $term = mb_strtolower(trim((string) $filters['q']));
            $pattern = "%{$term}%";

            $query->where(function ($query) use ($pattern): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$pattern]);
            });
        }

        return $query
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create a specialty from validated catalog data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Specialty
    {
        return Specialty::query()->create($data);
    }

    /**
     * Return a specialty for the detail resource.
     */
    public function load(Specialty $specialty): Specialty
    {
        return $specialty;
    }

    /**
     * Update a specialty with validated catalog data.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Specialty $specialty, array $data): Specialty
    {
        $specialty->update($data);

        return $specialty->refresh();
    }

    /**
     * Delete an unused specialty from the catalog.
     */
    public function delete(Specialty $specialty): void
    {
        $specialty->delete();
    }
}
