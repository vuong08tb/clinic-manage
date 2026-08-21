<?php

namespace App\Observers;

use App\Constants\ActivityLogAction;
use App\Constants\ActivityLogSubject;
use App\Models\User;
use App\Services\ActivityLogger;

/**
 * Record audit entries for user account lifecycle changes.
 */
class UserObserver
{
    /**
     * Attributes captured when an account is created.
     */
    private const CREATED_ATTRIBUTES = ['name', 'email', 'role_id', 'is_active'];

    /**
     * Profile attributes whose changes are audited. The password is watched so the trail
     * shows that a credential rotated; ActivityLogger replaces its value before writing.
     */
    private const PROFILE_ATTRIBUTES = ['name', 'email', 'password', 'role_id'];

    /**
     * Create a new user observer instance.
     */
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Record a newly created account.
     */
    public function created(User $user): void
    {
        $this->logger->logCreated($user, ActivityLogSubject::USER, self::CREATED_ATTRIBUTES);
    }

    /**
     * Record profile edits and activation changes as separate entries.
     *
     * UpdateUserRequest prohibits is_active, so a profile edit can never carry an
     * activation change; each call writes at most one of the two entries.
     */
    public function updated(User $user): void
    {
        $this->logger->logModelChange(
            $user,
            ActivityLogSubject::USER,
            ActivityLogAction::UPDATED,
            self::PROFILE_ATTRIBUTES,
        );

        $this->logger->logModelChange(
            $user,
            ActivityLogSubject::USER,
            ActivityLogAction::STATUS_CHANGED,
            ['is_active'],
        );
    }
}
