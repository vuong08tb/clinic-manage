<?php

namespace App\Http\Requests\Appointment;

use App\Constants\AppointmentMessage;
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
            'patient_id.exists' => AppointmentMessage::SELECTED_PATIENT_NOT_FOUND,
            'doctor_id.exists' => AppointmentMessage::SELECTED_DOCTOR_NOT_FOUND,
            'scheduled_at.after' => AppointmentMessage::APPOINTMENT_TIME_MUST_BE_FUTURE,
            'status.prohibited' => AppointmentMessage::STATUS_ASSIGNED_AUTOMATICALLY,
            'reason.max' => AppointmentMessage::REASON_TOO_LONG,
        ];
    }
}
