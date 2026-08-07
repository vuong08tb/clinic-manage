<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate filters used to paginate the user catalog.
 */
class ListUsersRequest extends FormRequest
{
    /**
     * Allow authenticated callers to submit list filters.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for user list filters.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages for user list filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_id.exists' => 'The selected role does not exist.',
            'is_active.boolean' => 'The active status must be true or false.',
            'per_page.max' => 'The page size may not be greater than 100.',
        ];
    }
}
