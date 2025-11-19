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
        $migration = include __DIR__.'/../database/migrations/create_ticket_levels_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_departments_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_tickets_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_ticket_assignments_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_escalation_requests_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_ticket_evaluations_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_agent_ratings_table.php.stub';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_risk_assessments_table.php.stub';
        $migration->up();
    }
}
