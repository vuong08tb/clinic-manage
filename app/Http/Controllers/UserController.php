<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * Expose administrator-only user management endpoints.
 */
class UserController extends Controller
{
    /**
     * Create a new user controller instance.
     */
    public function __construct(private readonly UserService $service) {}

    /**
     * Return a filtered and paginated user catalog.
     */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $users = $this->service->paginate($request->validated());

        return ApiResponse::paginated(
            UserResource::collection($users),
            'Users retrieved',
        );
    }

    /**
     * Create a managed user account.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return ApiResponse::resource(
            new UserResource($user),
            'User created',
            201,
        );
    }

    /**
     * Return a managed user account.
     */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::resource(
            new UserResource($this->service->load($user)),
            'User retrieved',
        );
    }

    /**
     * Update profile fields or the assigned role.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->service->update($user, $request->validated());

        return ApiResponse::resource(
            new UserResource($updatedUser),
            'User updated',
        );
    }

    /**
     * Deactivate a user without deleting its record.
     */
    public function destroy(User $user): JsonResponse
    {
        $deactivatedUser = $this->service->deactivate($user);

        return ApiResponse::resource(
            new UserResource($deactivatedUser),
            'User deactivated',
        );
    }

    /**
     * Activate or deactivate a managed user account.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->service->updateStatus(
            $user,
            $request->boolean('is_active'),
        );

        return ApiResponse::resource(
            new UserResource($updatedUser),
            'User status updated',
        );
    }
}
