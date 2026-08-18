<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to update an invoice's discount.
 */
class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating an invoice discount.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'discount' => ['required', 'numeric', 'min:0'],
            'examination_id' => ['prohibited'],
            'invoice_code' => ['prohibited'],
            'subtotal' => ['prohibited'],
            'total' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
