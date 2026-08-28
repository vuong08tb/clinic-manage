<?php

namespace App\Models;

use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represent a medicine catalog entry with unit price and stock quantity.
 */
#[Fillable(['code', 'name', 'unit', 'price', 'stock', 'is_active'])]
class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Search medicines by name or code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $operator = $query->getConnection()->getDriverName() === 'pgsql'
            ? 'ILIKE'
            : 'LIKE';
        $pattern = "%{$term}%";

        return $query->where(function (Builder $query) use ($operator, $pattern): void {
            $query
                ->where('name', $operator, $pattern)
                ->orWhere('code', $operator, $pattern);
        });
    }

    /**
     * Filter medicines by stock availability.
     */
    public function scopeStockStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'in_stock' => $query->where('stock', '>', 0),
            'out_of_stock' => $query->where('stock', '=', 0),
            default => $query,
        };
    }

    /**
     * Filter medicines at or below the low-stock threshold
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', (int) config('clinic.low_stock_threshold'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }
}
