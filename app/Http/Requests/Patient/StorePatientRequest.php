<?php

namespace App\Http\Requests\Patient;

use App\Constants\PatientMessage;
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
            'code.prohibited' => PatientMessage::CODE_GENERATED_AUTOMATICALLY,
            'gender.in' => PatientMessage::INVALID_GENDER,
            'date_of_birth.before_or_equal' => PatientMessage::DATE_OF_BIRTH_IN_FUTURE,
            'email.unique' => PatientMessage::EMAIL_ALREADY_TAKEN,
            'address.max' => PatientMessage::ADDRESS_TOO_LONG,
        ];
    }
}
