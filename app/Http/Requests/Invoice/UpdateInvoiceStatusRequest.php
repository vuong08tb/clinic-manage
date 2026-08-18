<?php

namespace App\Http\Requests\Invoice;

use App\Constants\InvoiceMessage;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate data used to cancel an invoice.
 */
class UpdateInvoiceStatusRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for cancelling an invoice.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Invoice::STATUS_CANCELLED])],
        ];
    }

    /**
     * Get custom validation messages for cancelling an invoice.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => InvoiceMessage::ONLY_CANCELLATION_SUPPORTED,
        ];
    }
}
