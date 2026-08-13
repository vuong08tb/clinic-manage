<?php

namespace App\Models;

use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represent the clinical findings recorded for an appointment.
 */
#[Fillable(['appointment_id', 'doctor_id', 'patient_id', 'diagnosis', 'notes', 'examined_at'])]
class Examination extends Model
{
    /** @use HasFactory<ExaminationFactory> */
    use HasFactory;

    /**
     * Return the appointment that produced this examination.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Return the doctor recorded on this examination.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Return the patient recorded on this examination.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'examined_at' => 'datetime',
        ];
    }
}
