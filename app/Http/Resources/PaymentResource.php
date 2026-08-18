<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a payment into its public API representation.
 */
class PaymentResource extends JsonResource
{
    /**
     * Convert the payment into a public representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'provider' => $this->provider,
            'provider_order_id' => $this->provider_order_id,
            'provider_capture_id' => $this->provider_capture_id,
            'paid_at' => $this->paid_at?->toISOString(),
            'note' => $this->note,
            'approval_url' => $this->when($this->approval_url !== null, $this->approval_url),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
