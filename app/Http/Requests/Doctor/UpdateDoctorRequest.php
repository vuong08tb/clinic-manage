<?php

namespace App\Http\Requests\Doctor;

use App\Constants\DoctorMessage;
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
                        DoctorMessage::UPDATE_FIELD_REQUIRED,
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
            'user_id.exists' => DoctorMessage::SELECTED_USER_NOT_FOUND,
            'user_id.unique' => DoctorMessage::USER_ALREADY_HAS_PROFILE,
            'specialty_id.exists' => DoctorMessage::SELECTED_SPECIALTY_NOT_FOUND,
            'license_number.unique' => DoctorMessage::LICENSE_NUMBER_ALREADY_TAKEN,
            'bio.max' => DoctorMessage::BIO_TOO_LONG,
        ];
    }
}
