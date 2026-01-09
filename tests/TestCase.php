<?php

namespace AichaDigital\Laratickets\Tests;

use AichaDigital\Laratickets\LaraticketsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
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

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/2024_11_01_000001_create_ticket_levels_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000002_create_departments_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000003_create_tickets_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000004_create_ticket_assignments_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000005_create_escalation_requests_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000006_create_ticket_evaluations_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000007_create_agent_ratings_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/2024_11_01_000008_create_risk_assessments_table.php';
        $migration->up();
    }
}
