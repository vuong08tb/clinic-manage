<?php

namespace App\Http\Requests\Appointment;

use App\Constants\AppointmentMessage;
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
            'scheduled_at' => ['sometimes', 'required', 'date', 'after:now'],
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
                        AppointmentMessage::UPDATE_FIELD_REQUIRED,
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
            'patient_id.prohibited' => AppointmentMessage::PATIENT_CANNOT_BE_CHANGED,
            'doctor_id.prohibited' => AppointmentMessage::DOCTOR_CANNOT_BE_CHANGED,
            'status.prohibited' => AppointmentMessage::USE_STATUS_ENDPOINT,
            'scheduled_at.after' => AppointmentMessage::APPOINTMENT_TIME_MUST_BE_FUTURE,
            'reason.max' => AppointmentMessage::REASON_TOO_LONG,
        ];
    }
}
