<?php

namespace App\Http\Requests\Patient;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate data used to create a patient profile.
 */
class StorePatientRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a patient profile.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(Patient::GENDERS)],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:patients,email'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages for creating a patient profile.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.prohibited' => 'The patient code is generated automatically.',
            'gender.in' => 'The gender must be male, female, or other.',
            'date_of_birth.before_or_equal' => 'The date of birth may not be in the future.',
            'email.unique' => 'The email has already been taken.',
            'address.max' => 'The address may not be greater than 255 characters.',
        ];
    }
}
