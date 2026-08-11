<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate patient profiles.
 */
class ListPatientsRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for patient list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for patient list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'The page size may not be greater than 100.',
        ];
    }
}
