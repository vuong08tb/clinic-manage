<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate data used to create an appointment.
 */
class StoreAppointmentRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating an appointment.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->whereNull('deleted_at'),
            ],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'status' => ['prohibited'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages for creating an appointment.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.exists' => 'The selected patient does not exist.',
            'doctor_id.exists' => 'The selected doctor does not exist.',
            'scheduled_at.after' => 'The appointment time must be in the future.',
            'status.prohibited' => 'The appointment status is assigned automatically.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}
