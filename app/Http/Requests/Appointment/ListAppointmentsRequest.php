<?php

namespace App\Http\Requests\Appointment;

use App\Constants\AppointmentMessage;
use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate filters used to paginate appointments.
 */
class ListAppointmentsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for appointment list filters.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'status' => ['nullable', 'string', Rule::in(Appointment::STATUSES)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for appointment list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.exists' => AppointmentMessage::SELECTED_DOCTOR_NOT_FOUND,
            'patient_id.exists' => AppointmentMessage::SELECTED_PATIENT_NOT_FOUND,
            'status.in' => AppointmentMessage::INVALID_STATUS,
            'date.date_format' => AppointmentMessage::INVALID_DATE_FORMAT,
            'per_page.max' => AppointmentMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
