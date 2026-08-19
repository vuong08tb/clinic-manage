<?php

namespace App\Http\Controllers;

use App\Constants\UserMessage;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

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
            UserMessage::LIST_RETRIEVED,
            Response::HTTP_OK,
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
            UserMessage::CREATED,
            Response::HTTP_CREATED,
        );
    }

    /**
     * Return a managed user account.
     */
    public function show(User $user): JsonResponse
    {
        return ApiResponse::resource(
            new UserResource($this->service->load($user)),
            UserMessage::RETRIEVED,
            Response::HTTP_OK,
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
            UserMessage::UPDATED,
            Response::HTTP_OK,
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
            UserMessage::DEACTIVATED,
            Response::HTTP_OK,
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
            UserMessage::STATUS_UPDATED,
            Response::HTTP_OK,
        );
    }
}
