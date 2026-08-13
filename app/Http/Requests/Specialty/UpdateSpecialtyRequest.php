<?php

namespace App\Http\Requests\Specialty;

use App\Constants\SpecialtyMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate data used to update a specialty.
 */
class UpdateSpecialtyRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a specialty.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('specialties', 'name')->ignore($this->route('specialty')),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'description'])) {
                    $validator->errors()->add(
                        'specialty',
                        SpecialtyMessage::UPDATE_FIELD_REQUIRED,
                    );
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a specialty.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => SpecialtyMessage::NAME_ALREADY_TAKEN,
            'description.max' => SpecialtyMessage::DESCRIPTION_TOO_LONG,
        ];
    }
}
