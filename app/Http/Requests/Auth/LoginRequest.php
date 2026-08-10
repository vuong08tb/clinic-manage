<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate credentials submitted to the login endpoint.
 */
class LoginRequest extends FormRequest
{
    /**
     * Allow unauthenticated clients to submit login credentials.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for a login request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
