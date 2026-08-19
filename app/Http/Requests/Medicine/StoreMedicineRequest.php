<?php

namespace App\Http\Requests\Medicine;

use App\Constants\MedicineMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create a medicine.
 */
class StoreMedicineRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a medicine.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:medicines,code'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages for creating a medicine.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => MedicineMessage::CODE_ALREADY_TAKEN,
        ];
    }
}
