<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

/**
 * Handle credential verification and Sanctum token lifecycle operations.
 */
class AuthService
{
    /**
     * Authenticate an active user and issue a new Bearer token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{token: string, user: User}
     *
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        $user = User::query()
            ->with('role.permissions')
            ->where('email', $credentials['email'])
            ->first();

        // Use one generic response for every credential failure to prevent account enumeration.
        if ($user === null
            || ! Hash::check($credentials['password'], $user->password)
            || ! $user->is_active) {
            throw new AuthenticationException('Invalid credentials');
        }

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ];
    }

    /**
     * Revoke only the personal access token used by the current request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /**
     * Load the role and permissions required by the current-user response.
     */
    public function currentUser(User $user): User
    {
        return $user->loadMissing('role.permissions');
    }
}
