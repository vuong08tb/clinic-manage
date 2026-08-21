<?php

namespace App\Services;

use App\Constants\ActivityLogAction;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Write audit entries describing changes made to business records.
 *
 * Entries are queued with DB::afterCommit, so a failing write can never roll back the
 * business operation it describes, and rolled-back work leaves no entry behind. The
 * payload is built eagerly and only the insert is deferred, which keeps the snapshot
 * tied to the moment of the change. Values are stored raw so before and after always
 * share the same shape.
 */
class ActivityLogger
{
    /**
     * Attribute names whose values must never reach the audit table.
     */
    private const REDACTED_KEYS = [
        'password',
        'remember_token',
        'api_token',
        'secret',
        'client_secret',
    ];

    private const REDACTED_PLACEHOLDER = '[REDACTED]';

    /**
     * Record an action against a subject with an explicit meta payload.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function log(string $subjectType, int $subjectId, string $action, ?array $meta = null): void
    {
        $attributes = [
            'user_id' => Auth::id(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'meta' => $meta === null ? null : $this->redact($meta),
        ];

        DB::afterCommit(static fn () => ActivityLog::query()->create($attributes));
    }

    /**
     * Record an action carrying an explicit before/after snapshot.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $context  Business context merged alongside the snapshot.
     */
    public function logChange(
        string $subjectType,
        int $subjectId,
        string $action,
        array $before,
        array $after,
        array $context = [],
    ): void {
        $this->log($subjectType, $subjectId, $action, $context + [
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * Record the creation of a model, capturing only the watched attributes.
     *
     * @param  array<int, string>  $watched
     */
    public function logCreated(Model $model, string $subjectType, array $watched): void
    {
        $this->log(
            $subjectType,
            (int) $model->getKey(),
            ActivityLogAction::CREATED,
            ['after' => Arr::only($model->getAttributes(), $watched)],
        );
    }

    /**
     * Record the deletion of a model, capturing only the watched attributes.
     *
     * @param  array<int, string>  $watched
     */
    public function logDeleted(Model $model, string $subjectType, array $watched): void
    {
        $this->log(
            $subjectType,
            (int) $model->getKey(),
            ActivityLogAction::DELETED,
            ['before' => Arr::only($model->getAttributes(), $watched)],
        );
    }

    /**
     * Record the watched attributes a save operation actually changed.
     *
     * Returns without writing when the save touched none of the watched attributes, which
     * keeps unrelated updates out of the audit trail.
     *
     * @param  array<int, string>  $watched
     * @param  array<string, mixed>  $context
     */
    public function logModelChange(
        Model $model,
        string $subjectType,
        string $action,
        array $watched,
        array $context = [],
    ): void {
        $after = Arr::only($model->getChanges(), $watched);

        if ($after === []) {
            return;
        }

        $this->logChange(
            $subjectType,
            (int) $model->getKey(),
            $action,
            Arr::only($model->getRawOriginal(), array_keys($after)),
            $after,
            $context,
        );
    }

    /**
     * Replace the value of any sensitive attribute, at any nesting depth.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function redact(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $meta[$key] = $this->redact($value);

                continue;
            }

            if (in_array($key, self::REDACTED_KEYS, true)) {
                $meta[$key] = self::REDACTED_PLACEHOLDER;
            }
        }

        return $meta;
    }
}
