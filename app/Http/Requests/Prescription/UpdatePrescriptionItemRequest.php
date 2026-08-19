<?php

namespace App\Http\Requests\Prescription;

use App\Constants\PrescriptionMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update the quantity, dosage, or usage instruction of a prescription item.
 */
class UpdatePrescriptionItemRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a prescription item.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'dosage' => ['sometimes', 'required', 'string'],
            'usage_instruction' => ['sometimes', 'nullable', 'string'],
            'medicine_id' => ['prohibited'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['quantity', 'dosage', 'usage_instruction'])) {
                    $validator->errors()->add(
                        'item',
                        PrescriptionMessage::ITEM_UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a prescription item.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'medicine_id.prohibited' => PrescriptionMessage::ITEM_MEDICINE_CANNOT_BE_CHANGED,
        ];
    }
}
