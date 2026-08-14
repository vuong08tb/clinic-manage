<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a prescription and its items into its public API representation.
 */
class PrescriptionResource extends JsonResource
{
    /**
     * Convert the prescription into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'examination_id' => $this->examination_id,
            'doctor_id' => $this->doctor_id,
            'notes' => $this->notes,
            'items' => PrescriptionItemResource::collection($this->whenLoaded('items')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
