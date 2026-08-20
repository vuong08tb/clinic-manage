<?php

namespace App\Http\Requests\Payment;

use App\Constants\PaymentMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate payments.
 */
class ListPaymentsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for payment list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'provider_order_id' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for payment list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.exists' => PaymentMessage::SELECTED_INVOICE_NOT_FOUND,
            'per_page.max' => PaymentMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
