<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Efficient UUID Cast for binary storage.
 *
 * Converts between string UUID representation in PHP and binary storage in database.
 * This provides 55% storage savings (16 bytes vs 36 bytes) with better index performance.
 *
 * Based on dyrynda/laravel-model-uuid but independent for Laravel 12 compatibility.
 *
 * @implements CastsAttributes<string, string>
 */
class EfficientUuid implements CastsAttributes
{
    /**
     * Cast the given value from the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // If already a valid UUID string, return as-is
        if (is_string($value) && Uuid::isValid($value)) {
            return $value;
        }

        // Convert binary to UUID string
        if (is_string($value) && strlen($value) === 16) {
            return Uuid::fromBytes($value)->toString();
        }

        // If it's a UUID object
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        return (string) $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // If already binary (16 bytes), return as-is
        if (is_string($value) && strlen($value) === 16 && ! Uuid::isValid($value)) {
            return $value;
        }

        // If it's a UUID object, convert to binary
        if ($value instanceof UuidInterface) {
            return $value->getBytes();
        }

        // If it's a valid UUID string, convert to binary
        if (is_string($value) && Uuid::isValid($value)) {
            return Uuid::fromString($value)->getBytes();
        }

        return (string) $value;
    }
}
