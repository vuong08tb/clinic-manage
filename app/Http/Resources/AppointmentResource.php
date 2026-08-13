<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform an appointment and its clinical context for API responses.
 */
class AppointmentResource extends JsonResource
{
    /**
     * Convert the appointment into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
