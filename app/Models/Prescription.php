<?php

namespace App\Models;

use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['examination_id', 'doctor_id', 'notes'])]
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory;

    public function examination()
    {
        return $this->belongsTo(Examination::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }
}
