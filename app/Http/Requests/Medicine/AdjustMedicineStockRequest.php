<?php

namespace App\Http\Requests\Medicine;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to adjust a medicine's stock quantity.
 */
class AdjustMedicineStockRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for adjusting medicine stock.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
