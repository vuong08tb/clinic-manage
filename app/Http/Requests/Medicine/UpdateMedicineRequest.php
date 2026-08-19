<?php

namespace App\Http\Requests\Medicine;

use App\Constants\MedicineMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update a medicine.
 */
class UpdateMedicineRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a medicine.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('medicines', 'code')->ignore($this->route('medicine')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['code', 'name', 'unit', 'price', 'stock', 'is_active'])) {
                    $validator->errors()->add(
                        'medicine',
                        MedicineMessage::UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a medicine.
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
