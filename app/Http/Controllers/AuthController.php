<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expose login, logout, and current-user endpoints for Sanctum authentication.
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Create a new authentication controller instance.
     */
    public function __construct(private readonly AuthService $service) {}

    /**
     * Validate credentials and return a newly issued Bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login($request->validated());

        return $this->ok([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ], 'Logged in');
    }

    /**
     * Revoke the Sanctum token that authenticated the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->service->logout($user);

        return $this->ok(message: 'Logged out');
    }

    /**
     * Return the authenticated user together with their role and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            new UserResource($this->service->currentUser($user)),
            'Current user retrieved',
        );
    }
}
