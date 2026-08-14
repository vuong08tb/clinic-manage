<?php

namespace App\Http\Requests\Examination;

use App\Constants\ExaminationMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create an examination from an appointment.
 */
class StoreExaminationRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating an examination.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'diagnosis' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'patient_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'examined_at' => ['prohibited'],
        ];
    }

    /**
     * Get custom validation messages for creating an examination.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'appointment_id.exists' => ExaminationMessage::SELECTED_APPOINTMENT_NOT_FOUND,
            'patient_id.prohibited' => ExaminationMessage::PATIENT_ASSIGNED_FROM_APPOINTMENT,
            'doctor_id.prohibited' => ExaminationMessage::DOCTOR_ASSIGNED_FROM_APPOINTMENT,
            'examined_at.prohibited' => ExaminationMessage::TIME_ASSIGNED_AUTOMATICALLY,
        ];
    }
}
