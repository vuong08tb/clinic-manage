<?php

namespace App\Http\Requests\Invoice;

use App\Constants\InvoiceMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create an invoice from an examination.
 */
class StoreInvoiceRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating an invoice.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom validation messages for creating an invoice.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examination_id.exists' => InvoiceMessage::SELECTED_EXAMINATION_NOT_FOUND,
        ];
    }
}
