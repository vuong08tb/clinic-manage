<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represent a patient profile and its searchable contact details.
 */
#[Fillable(['code', 'full_name', 'gender', 'date_of_birth', 'phone', 'email', 'address'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Define the supported patient gender values.
     *
     * @var array<int, string>
     */
    public const GENDERS = ['male', 'female', 'other'];

    /**
     * Search patients by name, phone number, or patient code.
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
                ->where('full_name', $operator, $pattern)
                ->orWhere('phone', $operator, $pattern)
                ->orWhere('code', $operator, $pattern);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'deleted_at' => 'datetime',
        ];
    }
}
