<?php

namespace App\Http\Requests\Patient;

use App\Constants\PatientMessage;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update a patient profile.
 */
class UpdatePatientRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a patient profile.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'gender' => ['sometimes', 'required', 'string', Rule::in(Patient::GENDERS)],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('patients', 'email')->ignore($this->route('patient')),
            ],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny([
                    'full_name',
                    'gender',
                    'date_of_birth',
                    'phone',
                    'email',
                    'address',
                ])) {
                    $validator->errors()->add(
                        'patient',
                        PatientMessage::UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a patient profile.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.prohibited' => PatientMessage::CODE_CANNOT_BE_CHANGED,
            'gender.in' => PatientMessage::INVALID_GENDER,
            'date_of_birth.before_or_equal' => PatientMessage::DATE_OF_BIRTH_IN_FUTURE,
            'email.unique' => PatientMessage::EMAIL_ALREADY_TAKEN,
            'address.max' => PatientMessage::ADDRESS_TOO_LONG,
        ];
    }
}
