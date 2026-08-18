<?php

namespace App\Http\Requests\Medicine;

use App\Constants\MedicineMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate filters used to paginate medicines.
 */
class ListMedicinesRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for medicine list filters.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'stock_status' => ['nullable', 'string', Rule::in(['in_stock', 'out_of_stock'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for medicine list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => MedicineMessage::PAGE_SIZE_TOO_LARGE,
            'stock_status.in' => MedicineMessage::INVALID_STOCK_STATUS,
        ];
    }
}
