<?php

namespace AichaDigital\Laratickets\Tests;

use AichaDigital\Laratickets\LaraticketsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    // Package migrations are discovered from the ServiceProvider's
    // loadMigrationsFrom() and run by RefreshDatabase, so a new migration is
    // picked up automatically — there is no hardcoded include/->up() list to
    // keep in sync. See the umbrella CLAUDE.md lesson (2026-06-27).
    use RefreshDatabase;

    /**
     * Deterministic UUID v7 fixtures for tests that need actor IDs.
     *
     * Same prefix as larabill (0194a000-…) so consumers loading both packages
     * recognize them as AichaDigital test fixtures.
     */
    public const USER_UUID_1 = '0194a000-0000-7000-8000-000000000001';

    public const USER_UUID_2 = '0194a000-0000-7000-8000-000000000002';

    public const USER_UUID_3 = '0194a000-0000-7000-8000-000000000003';

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AichaDigital\\Laratickets\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaraticketsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
