<?php

namespace App\Models;

use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represent a scheduled clinical appointment.
 */
#[Fillable(['patient_id', 'doctor_id', 'scheduled_at', 'status', 'reason'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Define the supported appointment lifecycle statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    /**
     * Return the patient assigned to this appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * Return the doctor assigned to this appointment.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Search appointments by reason, patient details, or doctor account details.
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
                ->where('reason', $operator, $pattern)
                ->orWhereHas('patient', function (Builder $query) use ($operator, $pattern): void {
                    $query
                        ->where('full_name', $operator, $pattern)
                        ->orWhere('phone', $operator, $pattern)
                        ->orWhere('code', $operator, $pattern);
                })
                ->orWhereHas('doctor.user', function (Builder $query) use ($operator, $pattern): void {
                    $query
                        ->where('name', $operator, $pattern)
                        ->orWhere('email', $operator, $pattern);
                });
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
            'scheduled_at' => 'datetime',
        ];
    }
}
