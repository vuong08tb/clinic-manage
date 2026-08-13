<?php

namespace App\Http\Requests\Appointment;

use App\Constants\AppointmentMessage;
use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate data used to transition an appointment status.
 */
class UpdateAppointmentStatusRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for transitioning an appointment status.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(Appointment::STATUSES),
            ],
        ];
    }

    /**
     * Get custom validation messages for appointment status transitions.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => AppointmentMessage::INVALID_STATUS,
        ];
    }
}
