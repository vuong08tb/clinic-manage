<?php

namespace App\Http\Resources;

use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform an invoice and its billed examination into its public API representation.
 */
class InvoiceResource extends JsonResource
{
    /**
     * Convert the invoice into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prescription = $this->relationLoaded('examination') && $this->examination->relationLoaded('prescription')
            ? $this->examination->prescription
            : null;

        return [
            'id' => $this->id,
            'examination_id' => $this->examination_id,
            'invoice_code' => $this->invoice_code,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toISOString(),
            'examination' => new ExaminationResource($this->whenLoaded('examination')),
            'items' => $this->when(
                $prescription instanceof Prescription,
                fn () => PrescriptionItemResource::collection($prescription->items),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
