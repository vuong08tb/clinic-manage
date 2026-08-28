<?php

namespace App\Http\Requests\Medicine;

use App\Constants\MedicineMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate medicines.
 */
class ListLowStockMedicinesRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['per_page.max' => MedicineMessage::PAGE_SIZE_TOO_LARGE];
    }
}