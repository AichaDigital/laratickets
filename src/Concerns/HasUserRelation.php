<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that have user foreign key columns.
 *
 * This trait provides dynamic relationship methods to the configured User model.
 * The trait is agnostic to the user ID type - it reads from config('laratickets.user.id_type')
 * and supports int, uuid, and ulid types.
 *
 * Note: uuid_binary was removed in v1.0 due to incompatibility with FilamentPHP v4.
 * See ADR-002 for details.
 *
 * Supported user_id types:
 *
 * - 'int' or 'integer': Standard auto-increment
 * - 'uuid': String UUID v7 - RECOMMENDED
 * - 'ulid': String ULID
 *
 * Usage:
 *   use HasUserRelation;
 *
 *   // Define which columns reference users:
 *   protected array $userColumns = ['created_by', 'resolved_by'];
 *
 *   // The trait will provide relationships like:
 *   // creator(), resolver(), user(), agent(), etc.
 */
trait HasUserRelation
{
    /**
     * Get the user columns that should be tracked.
     *
     * Override this in your model to specify which columns reference users.
     *
     * @return array<string>
     */
    protected function getUserColumns(): array
    {
        // Default user columns, override in model if different
        return $this->userColumns ?? ['user_id'];
    }

    /**
     * Get the User model class from configuration.
     */
    protected function getUserModelClass(): string
    {
        return config('laratickets.user.model', 'App\\Models\\User');
    }

    /**
     * Get the user ID column name from configuration.
     */
    protected function getUserIdColumnName(): string
    {
        return config('laratickets.user.id_column', 'id');
    }

    /**
     * Get the user that created this record (for models with created_by).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'created_by',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the user that resolved this record (for models with resolved_by).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'resolved_by',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the user relationship (for models with user_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'user_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the agent relationship (for models with agent_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'agent_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the rater relationship (for models with rater_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'rater_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the requester relationship (for models with requester_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'requester_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the approver relationship (for models with approver_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'approver_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the evaluator relationship (for models with evaluator_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'evaluator_id',
            $this->getUserIdColumnName()
        );
    }

    /**
     * Get the assessor relationship (for models with assessor_id).
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(
            $this->getUserModelClass(),
            'assessor_id',
            $this->getUserIdColumnName()
        );
    }
}
