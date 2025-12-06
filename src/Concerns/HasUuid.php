<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Concerns;

use AichaDigital\Laratickets\Casts\EfficientUuid;
use AichaDigital\Laratickets\Support\MigrationHelper;
use Illuminate\Support\Str;

/**
 * Agnostic UUID trait for Eloquent models.
 *
 * Supports multiple UUID strategies based on configuration:
 * - 'uuid': String UUID v7 (36 chars) - uses Laravel native
 * - 'uuid_binary': Binary UUID (16 bytes) - uses Dyrynda EfficientUuid cast
 *
 * For binary UUID, requires dyrynda/laravel-model-uuid package.
 * The trait automatically configures the model based on laratickets.user.id_type config.
 *
 * Usage:
 *   use HasUuid;
 *
 * For binary UUID, ensure your migration uses:
 *   MigrationHelper::uuidPrimaryKey($table);
 *
 * For string UUID:
 *   $table->uuid('id')->primary();
 */
trait HasUuid
{
    /**
     * Boot the trait and set up UUID generation.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model): void {
            $keyName = $model->getKeyName();

            if (empty($model->{$keyName})) {
                // Generate UUID v7 (ordered) for better index performance
                $uuid = (string) Str::orderedUuid();

                // For both string and binary storage, we set the string value
                // The EfficientUuid cast handles conversion to binary on save
                $model->{$keyName} = $uuid;
            }
        });
    }

    /**
     * Initialize the trait - configure model for UUID usage.
     */
    protected function initializeHasUuid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * Get the casts array, adding EfficientUuid for binary UUID if needed.
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        $casts = parent::getCasts();
        $idType = MigrationHelper::getUserIdType();

        if ($idType === 'uuid_binary') {
            // Add EfficientUuid cast for binary storage
            $casts[$this->getKeyName()] = EfficientUuid::class;
        }

        return $casts;
    }

    /**
     * Get the route key for the model.
     *
     * For binary UUID, this returns the string representation.
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Resolve the route binding for UUID.
     *
     * Handles both string and binary UUID lookups.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        // For binary UUID storage, convert string UUID to binary for query
        if ($this->usesBinaryUuid() && $field === $this->getKeyName()) {
            if (is_string($value) && \Ramsey\Uuid\Uuid::isValid($value)) {
                $value = \Ramsey\Uuid\Uuid::fromString($value)->getBytes();
            }
        }

        return $this->where($field, $value)->first();
    }

    /**
     * Check if the model is using binary UUID storage.
     */
    public function usesBinaryUuid(): bool
    {
        return MigrationHelper::getUserIdType() === 'uuid_binary';
    }

    /**
     * Get the UUID column name (for compatibility with dyrynda package).
     */
    public function uuidColumn(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the UUID columns (for compatibility with dyrynda package).
     *
     * @return array<string>
     */
    public function uuidColumns(): array
    {
        return [$this->getKeyName()];
    }
}
