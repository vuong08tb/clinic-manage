<?php

namespace App\Http\Requests\User;

use App\Constants\UserMessage;
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
            'role_id.exists' => UserMessage::SELECTED_ROLE_NOT_FOUND,
            'is_active.boolean' => UserMessage::ACTIVE_STATUS_MUST_BE_BOOLEAN,
            'per_page.max' => UserMessage::PAGE_SIZE_TOO_LARGE,
        ];
    }
}
