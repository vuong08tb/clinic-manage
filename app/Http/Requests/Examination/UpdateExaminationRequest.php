<?php

namespace App\Http\Requests\Examination;

use App\Constants\ExaminationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate clinical fields used to update an examination.
 */
class UpdateExaminationRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating an examination.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'diagnosis' => ['sometimes', 'required', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'appointment_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'examined_at' => ['prohibited'],
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
                if (! $this->hasAny(['diagnosis', 'notes'])) {
                    $validator->errors()->add(
                        'examination',
                        ExaminationMessage::UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating an examination.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'appointment_id.prohibited' => ExaminationMessage::APPOINTMENT_CANNOT_BE_CHANGED,
            'patient_id.prohibited' => ExaminationMessage::PATIENT_CANNOT_BE_CHANGED,
            'doctor_id.prohibited' => ExaminationMessage::DOCTOR_CANNOT_BE_CHANGED,
            'examined_at.prohibited' => ExaminationMessage::TIME_CANNOT_BE_CHANGED,
        ];
    }
}
