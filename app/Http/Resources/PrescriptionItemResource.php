<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a prescription item into its public API representation.
 */
class PrescriptionItemResource extends JsonResource
{
    /**
     * Convert the prescription item into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medicine_id' => $this->medicine_id,
            'quantity' => $this->quantity,
            'dosage' => $this->dosage,
            'usage_instruction' => $this->usage_instruction,
            'medicine' => $this->whenLoaded('medicine', fn (): array => [
                'id' => $this->medicine->id,
                'code' => $this->medicine->code,
                'name' => $this->medicine->name,
                'unit' => $this->medicine->unit,
                'price' => $this->medicine->price,
                'is_active' => $this->medicine->is_active,
            ]),
        ];
    }
}
