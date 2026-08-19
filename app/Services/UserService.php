<?php

namespace App\Services;

use App\Constants\DoctorMessage;
use App\Constants\UserMessage;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Handle user management rules and account status transitions.
 */
class UserService
{
    /**
     * Paginate users with validated management filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()->with('role');

        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $term = mb_strtolower(trim((string) $filters['q']));
            $pattern = "%{$term}%";

            $query->where(function ($query) use ($pattern): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            });
        }

        if (isset($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $isActive = filter_var(
                $filters['is_active'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        return $query
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Create an active user and load its assigned role.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $data['is_active'] = true;
        $user = User::query()->create($data);

        return $user->load('role');
    }

    /**
     * Load the role required by the management resource.
     */
    public function load(User $user): User
    {
        return $user->loadMissing('role');
    }

    /**
     * Update profile fields and protect the final active administrator.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        if (! array_key_exists('role_id', $data)) {
            $user->update($data);

            return $user->refresh()->load('role');
        }

        return $this->mutateWithAdminGuard(
            $user,
            'role_id',
            function (User $lockedUser) use ($data): void {
                $this->assertDoctorRoleChangeAllowed(
                    $lockedUser,
                    (int) $data['role_id'],
                );
                $lockedUser->update($data);
            },
            (int) $data['role_id'],
        );
    }

    /**
     * Deactivate an account without deleting its user record.
     */
    public function deactivate(User $user): User
    {
        return $this->setInactiveWithGuard($user);
    }

    /**
     * Activate or deactivate a managed user account.
     */
    public function updateStatus(User $user, bool $isActive): User
    {
        if (! $isActive) {
            return $this->setInactiveWithGuard($user);
        }

        return DB::transaction(function () use ($user): User {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $lockedUser->update(['is_active' => true]);

            return $lockedUser->refresh()->load('role');
        });
    }

    /**
     * Deactivate a locked account and revoke all issued API tokens.
     */
    private function setInactiveWithGuard(User $user): User
    {
        return $this->mutateWithAdminGuard(
            $user,
            'is_active',
            function (User $lockedUser): void {
                $lockedUser->update(['is_active' => false]);
                $lockedUser->tokens()->delete();
            },
        );
    }

    /**
     * Serialize mutations that can reduce the number of active administrators.
     *
     * @param  Closure(User): void  $mutation
     */
    private function mutateWithAdminGuard(
        User $user,
        string $field,
        Closure $mutation,
        ?int $newRoleId = null,
    ): User {
        return DB::transaction(function () use ($user, $field, $mutation, $newRoleId): User {
            $adminRoleId = Role::query()
                ->where('name', 'ADMIN')
                ->value('id');

            if ($adminRoleId === null) {
                throw new LogicException(UserMessage::ADMIN_ROLE_NOT_CONFIGURED);
            }

            $activeAdminIds = User::query()
                ->where('role_id', $adminRoleId)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            $reducesActiveAdmins = $field === 'is_active'
                || (int) $lockedUser->role_id !== $newRoleId;

            if ($reducesActiveAdmins) {
                $this->assertNotLastActiveAdmin(
                    $lockedUser,
                    $activeAdminIds,
                    $adminRoleId,
                    $field,
                );
            }

            $mutation($lockedUser);

            return $lockedUser->refresh()->load('role');
        });
    }

    /**
     * Reject mutations that would remove the final active administrator.
     *
     * @param  Collection<int, int>  $activeAdminIds
     *
     * @throws ValidationException
     */
    private function assertNotLastActiveAdmin(
        User $user,
        Collection $activeAdminIds,
        int $adminRoleId,
        string $field,
    ): void {
        $isActiveAdmin = (int) $user->role_id === $adminRoleId && $user->is_active;

        if (! $isActiveAdmin || $activeAdminIds->count() > 1) {
            return;
        }

        $message = $field === 'role_id'
            ? UserMessage::LAST_ACTIVE_ADMIN_ROLE_CHANGE
            : UserMessage::LAST_ACTIVE_ADMIN_DEACTIVATION;

        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }

    /**
     * Prevent a doctor profile from being assigned to a non-doctor user.
     *
     * @throws ValidationException
     */
    private function assertDoctorRoleChangeAllowed(User $user, int $newRoleId): void
    {
        if (! $user->doctor()->exists()) {
            return;
        }

        $newRoleName = Role::query()->whereKey($newRoleId)->value('name');

        if ($newRoleName === 'DOCTOR') {
            return;
        }

        throw ValidationException::withMessages([
            'role_id' => [DoctorMessage::USER_WITH_PROFILE_MUST_KEEP_DOCTOR_ROLE],
        ]);
    }
}
