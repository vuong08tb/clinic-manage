<?php

namespace App\Http\Requests\Doctor;

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
            'user_id.exists' => 'The selected user does not exist.',
            'user_id.unique' => 'The selected user already has a doctor profile.',
            'specialty_id.exists' => 'The selected specialty does not exist.',
            'license_number.unique' => 'The license number has already been taken.',
            'bio.max' => 'The bio may not be greater than 5000 characters.',
        ];
    }
}
