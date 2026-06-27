<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Support\ActorId;
use AichaDigital\Laratickets\Support\SystemActor;

/**
 * ActorId is the single place the domain reads the configured user id column,
 * keeping config('laratickets.user.id_column') out of every service hot path.
 */
describe('ActorId::of', function () {
    it('resolves the default id column from an actor', function () {
        $actor = new class
        {
            public string $id = 'abc';
        };

        expect(ActorId::of($actor))->toBe('abc');
    });

    it('honors a custom configured id column', function () {
        config()->set('laratickets.user.id_column', 'uuid');

        $actor = new class
        {
            public string $uuid = 'xyz';

            public string $id = 'should-not-be-used';
        };

        expect(ActorId::of($actor))->toBe('xyz');
    });

    it('resolves SystemActor to a null id regardless of column', function () {
        expect(ActorId::of(new SystemActor))->toBeNull();

        config()->set('laratickets.user.id_column', 'uuid');
        expect(ActorId::of(new SystemActor))->toBeNull();
    });
});
