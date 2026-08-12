<?php

namespace App\Http\Requests\User;

use App\Constants\UserMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate activation changes for a managed user account.
 */
class UpdateUserStatusRequest extends FormRequest
{
    /**
     * Allow the RBAC middleware to determine access to this operation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for updating account status.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages for updating account status.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => UserMessage::ACTIVE_STATUS_REQUIRED,
            'is_active.boolean' => UserMessage::ACTIVE_STATUS_MUST_BE_BOOLEAN,
        ];
    }
}
