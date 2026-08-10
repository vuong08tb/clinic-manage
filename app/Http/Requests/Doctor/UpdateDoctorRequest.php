<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update a doctor profile.
 */
class UpdateDoctorRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a doctor profile.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('doctors', 'user_id')->ignore($this->route('doctor')),
            ],
            'specialty_id' => ['sometimes', 'required', 'integer', 'exists:specialties,id'],
            'license_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('doctors', 'license_number')->ignore($this->route('doctor')),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['user_id', 'specialty_id', 'license_number', 'bio'])) {
                    $validator->errors()->add(
                        'doctor',
                        'At least one doctor field must be provided.',
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a doctor profile.
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
