<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Concerns;

use Illuminate\Support\Str;

/**
 * Agnostic UUID trait for Eloquent models.
 *
 * Generates real UUID v7 keys via Str::uuid7() (STD-001, ADR-001). Do NOT
 * swap in Str::orderedUuid(): it is a v4 COMB — time-ordered bytes, but the
 * version nibble reads 4 — so it breaks the v7 key contract this package
 * publishes (AID-1420).
 * The trait configures the model to use UUID as primary key.
 *
 * Note: uuid_binary was removed in v1.0 due to incompatibility with FilamentPHP v4.
 * See ADR-002 for details.
 *
 * Usage:
 *   use HasUuid;
 *
 * In your migration:
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
                // Real UUID v7: time-ordered bytes AND version nibble 7 (AID-1420).
                $model->{$keyName} = (string) Str::uuid7();
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
     * Get the route key for the model.
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the UUID column name (for compatibility).
     */
    public function uuidColumn(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the UUID columns (for compatibility).
     *
     * @return array<string>
     */
    public function uuidColumns(): array
    {
        return [$this->getKeyName()];
    }
}
