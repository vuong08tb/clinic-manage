<?php

namespace App\Http\Requests\Prescription;

use App\Constants\PrescriptionMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create a prescription from an examination.
 */
class StorePrescriptionRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a prescription.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'notes' => ['nullable', 'string'],
            'doctor_id' => ['prohibited'],

            'items' => ['nullable', 'array'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.dosage' => ['required', 'string'],
            'items.*.usage_instruction' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom validation messages for creating a prescription.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examination_id.exists' => PrescriptionMessage::SELECTED_EXAMINATION_NOT_FOUND,
            'doctor_id.prohibited' => PrescriptionMessage::DOCTOR_ASSIGNED_FROM_EXAMINATION,
        ];
    }
}
