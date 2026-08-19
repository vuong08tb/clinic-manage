<?php

namespace App\Http\Requests\Prescription;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to add a medicine line to an existing prescription.
 */
class AddPrescriptionItemRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for adding a prescription item.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'dosage' => ['required', 'string'],
            'usage_instruction' => ['nullable', 'string'],
        ];
    }
}
