<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Support;

/**
 * Resolves the configured user id from an actor.
 *
 * This is the single place the domain reads
 * `config('laratickets.user.id_column')`, so services no longer scatter that
 * lookup across every mutating method. Pass any user model, or a
 * {@see SystemActor} for non-human operations (which resolves to null).
 */
final class ActorId
{
    public static function of(mixed $actor): mixed
    {
        $column = config('laratickets.user.id_column', 'id');

        return $actor->{$column} ?? null;
    }
}
