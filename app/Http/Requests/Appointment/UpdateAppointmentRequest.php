<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update a scheduled appointment.
 */
class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating an appointment.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['prohibited'],
            'doctor_id' => ['prohibited'],
            'status' => ['prohibited'],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
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
                if (! $this->hasAny(['scheduled_at', 'reason'])) {
                    $validator->errors()->add(
                        'appointment',
                        'At least one appointment field must be provided.',
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating an appointment.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_id.prohibited' => 'The appointment patient cannot be changed.',
            'doctor_id.prohibited' => 'The appointment doctor cannot be changed.',
            'status.prohibited' => 'Use the appointment status endpoint to change status.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}
