<?php

namespace App\Http\Requests\Doctor;

use App\Constants\DoctorMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create a doctor profile.
 */
class StoreDoctorRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a doctor profile.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:doctors,user_id'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'license_number' => ['required', 'string', 'max:255', 'unique:doctors,license_number'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom validation messages for creating a doctor profile.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => DoctorMessage::SELECTED_USER_NOT_FOUND,
            'user_id.unique' => DoctorMessage::USER_ALREADY_HAS_PROFILE,
            'specialty_id.exists' => DoctorMessage::SELECTED_SPECIALTY_NOT_FOUND,
            'license_number.unique' => DoctorMessage::LICENSE_NUMBER_ALREADY_TAKEN,
            'bio.max' => DoctorMessage::BIO_TOO_LONG,
        ];
    }
}
