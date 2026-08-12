<?php

namespace App\Http\Requests\User;

use App\Constants\UserMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate profile and role changes for a managed user account.
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating a user.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'is_active' => ['prohibited'],
        ];
    }

    /**
     * Configure validation that depends on the complete update payload.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['name', 'email', 'password', 'role_id'])) {
                    $validator->errors()->add('user', UserMessage::UPDATE_FIELD_REQUIRED);
                }
            },
        ];
    }

    /**
     * Get custom validation messages for updating a user.
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
