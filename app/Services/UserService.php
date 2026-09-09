<?php

namespace App\Services;

use App\Constants\DoctorMessage;
use App\Constants\UserMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Handle user management rules and account status transitions.
 */
class UserService
{

    private const CREATED_AS_ACTIVE = true;

    private ?int $adminRoleId = null;

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
            $pattern = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($query) use ($pattern): void {
                $query
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$pattern]);
            });
        }

        if (isset($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
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
        $data['is_active'] = self::CREATED_AS_ACTIVE;
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

        $newRoleId = (int) $data['role_id'];

        return DB::transaction(function () use ($user, $data, $newRoleId): User {
            $lockedUser = $this->lockUserAndActiveAdmins($user);

            if ((int) $lockedUser->role_id !== $newRoleId) {
                $this->assertNotLastActiveAdmin($lockedUser, 'role_id');
            }

            $this->assertDoctorRoleChangeAllowed($lockedUser, $newRoleId);
            $lockedUser->update($data);

            return $lockedUser->refresh()->load('role');
        });
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
        return DB::transaction(function () use ($user): User {
            $lockedUser = $this->lockUserAndActiveAdmins($user);

            $this->assertNotLastActiveAdmin($lockedUser, 'is_active');

            $lockedUser->update(['is_active' => false]);
            $lockedUser->tokens()->delete();

            return $lockedUser->refresh()->load('role');
        });
    }

    /**
     * Lock the target account together with every active administrator.
     *
     * Both row sets are taken in one statement ordered by id, so concurrent
     * transactions always request the same rows in the same order and cannot end up
     * each holding a row the other one still needs.
     */
    private function lockUserAndActiveAdmins(User $user): User
    {
        $adminRoleId = $this->adminRoleId();

        $lockedRows = User::query()
            ->where(function ($query) use ($user, $adminRoleId): void {
                $query
                    ->where('id', $user->getKey())
                    ->orWhere(function ($query) use ($adminRoleId): void {
                        $query
                            ->where('role_id', $adminRoleId)
                            ->where('is_active', true);
                    });
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lockedUser = $lockedRows->firstWhere('id', $user->getKey());

        if ($lockedUser === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$user->getKey()]);
        }

        return $lockedUser;
    }

    /**
     * Resolve the ADMIN role id, failing loudly when the role is missing.
     */
    private function adminRoleId(): int
    {
        if ($this->adminRoleId !== null) {
            return $this->adminRoleId;
        }

        $adminRoleId = Role::query()
            ->where('name', Role::ADMIN)
            ->value('id');

        if ($adminRoleId === null) {
            throw new LogicException(UserMessage::ADMIN_ROLE_NOT_CONFIGURED);
        }

        return $this->adminRoleId = (int) $adminRoleId;
    }

    /**
     * Reject mutations that would remove the final active administrator.
     *
     * Reading without a fresh lock is safe here: lockUserAndActiveAdmins already holds
     * every row this query can match.
     *
     * @throws ValidationException
     */
    private function assertNotLastActiveAdmin(User $user, string $field): void
    {
        $isActiveAdmin = (int) $user->role_id === $this->adminRoleId() && $user->is_active;

        if (! $isActiveAdmin) {
            return;
        }

        $anotherActiveAdminExists = User::query()
            ->where('role_id', $this->adminRoleId())
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($anotherActiveAdminExists) {
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

        if ($newRoleName === Role::DOCTOR) {
            return;
        }

        throw ValidationException::withMessages([
            'role_id' => [DoctorMessage::USER_WITH_PROFILE_MUST_KEEP_DOCTOR_ROLE],
        ]);
    }
}