<?php

namespace App\Http\Requests\Invoice;

use App\Constants\InvoiceMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate invoices.
 */
class ListInvoicesRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for invoice list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:unpaid,paid,cancelled'],
            'examination_id' => ['nullable', 'integer', 'exists:examinations,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for invoice list filters.
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
