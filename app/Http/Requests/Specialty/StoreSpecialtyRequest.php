<?php

namespace App\Http\Requests\Specialty;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create a specialty.
 */
class StoreSpecialtyRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a specialty.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:specialties,name'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom validation messages for creating a specialty.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'The specialty name has already been taken.',
            'description.max' => 'The description may not be greater than 2000 characters.',
        ];
    }
}
