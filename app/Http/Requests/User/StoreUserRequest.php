<?php

namespace App\Http\Requests\User;

use App\Constants\UserMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate data used to create a managed user account.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for creating a user.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['prohibited'],
        ];
    }

    /**
     * Get custom validation messages for creating a user.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => UserMessage::EMAIL_ALREADY_TAKEN,
            'password.confirmed' => UserMessage::PASSWORD_CONFIRMATION_MISMATCH,
            'role_id.exists' => UserMessage::SELECTED_ROLE_NOT_FOUND,
            'is_active.prohibited' => UserMessage::USE_STATUS_ENDPOINT,
        ];
    }
}
