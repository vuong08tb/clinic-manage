<?php

namespace App\Http\Requests\Doctor;

use App\Constants\DoctorMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate doctor profiles.
 */
class ListDoctorsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for doctor list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for doctor list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'specialty_id.exists' => DoctorMessage::SELECTED_SPECIALTY_NOT_FOUND,
            'per_page.max' => DoctorMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
