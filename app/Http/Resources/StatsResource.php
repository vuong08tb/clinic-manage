<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform the clinic overview figures into their public API representation.
 */
class StatsResource extends JsonResource
{
    /**
     * Convert the overview figures into a public representation.
     *
     * Revenue is returned as a fixed two-decimal string, matching how InvoiceResource and
     * PaymentResource expose monetary values.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_patients' => (int) $this->resource['total_patients'],
            'appointments_today' => (int) $this->resource['appointments_today'],
            'revenue_this_month' => (string) $this->resource['revenue_this_month'],
            'low_stock_medicines' => (int) $this->resource['low_stock_medicines'],
        ];
    }
}
