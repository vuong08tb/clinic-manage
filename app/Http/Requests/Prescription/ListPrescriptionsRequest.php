<?php

namespace App\Http\Requests\Prescription;

use App\Constants\PrescriptionMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate prescriptions.
 */
class ListPrescriptionsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for prescription list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'examination_id' => ['nullable', 'integer', 'exists:examinations,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for prescription list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'examination_id.exists' => PrescriptionMessage::SELECTED_EXAMINATION_NOT_FOUND,
            'doctor_id.exists' => PrescriptionMessage::SELECTED_DOCTOR_NOT_FOUND,
            'patient_id.exists' => PrescriptionMessage::SELECTED_PATIENT_NOT_FOUND,
            'per_page.max' => PrescriptionMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
