<?php

namespace App\Http\Controllers;

use App\Constants\AuthMessage;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expose login, logout, and current-user endpoints for Sanctum authentication.
 */
class AuthController extends Controller
{
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

        return ApiResponse::success([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => (new UserResource($result['user']))->resolve($request),
        ], AuthMessage::LOGGED_IN, Response::HTTP_OK);
    }

    /**
     * Revoke the Sanctum token that authenticated the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->service->logout($user);

        return ApiResponse::success(
            message: AuthMessage::LOGGED_OUT,
            status: Response::HTTP_OK,
        );
    }

    /**
     * Return the authenticated user together with their role and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::resource(
            new UserResource($this->service->currentUser($user)),
            AuthMessage::CURRENT_USER_RETRIEVED,
            Response::HTTP_OK,
        );
    }
}
