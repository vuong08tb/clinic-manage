<?php

namespace App\Http\Requests\Prescription;

use App\Constants\PrescriptionMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate the prescription's own fields, which is only its notes.
 */
class UpdatePrescriptionRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a prescription.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string'],
            'examination_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            // Items move medicine stock, so they only change through the item
            // endpoints where that adjustment runs inside a transaction.
            'items' => ['prohibited'],
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
                if (! $this->hasAny(['notes'])) {
                    $validator->errors()->add(
                        'prescription',
                        PrescriptionMessage::UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a prescription.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examination_id.prohibited' => PrescriptionMessage::EXAMINATION_CANNOT_BE_CHANGED,
            'doctor_id.prohibited' => PrescriptionMessage::DOCTOR_ASSIGNED_FROM_EXAMINATION,
            'items.prohibited' => PrescriptionMessage::ITEMS_MANAGED_SEPARATELY,
        ];
    }
}
