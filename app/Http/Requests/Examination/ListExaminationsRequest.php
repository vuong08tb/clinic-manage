<?php

namespace App\Http\Requests\Examination;

use App\Constants\ExaminationMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate examinations.
 */
class ListExaminationsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for examination list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for examination list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.exists' => ExaminationMessage::SELECTED_DOCTOR_NOT_FOUND,
            'patient_id.exists' => ExaminationMessage::SELECTED_PATIENT_NOT_FOUND,
            'per_page.max' => ExaminationMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
