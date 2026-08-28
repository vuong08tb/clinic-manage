<?php

namespace App\Http\Resources;

use Illuminate\Support\Arr;

/**
 * Billing view of a prescription item: identical to the prescription view minus
 * stock, which cashiers hold no MEDICINES permission to see.
 */
class InvoiceItemResource extends PrescriptionItemResource
{
    /**
     * Expose the medicine without its stock level.
     *
     * @return array<string, mixed>
     */
    protected function medicineFields(): array
    {
        return Arr::except(parent::medicineFields(), ['stock']);
    }
}
