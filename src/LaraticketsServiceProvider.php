<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets;

use AichaDigital\Laratickets\Commands\InstallCommand;
use AichaDigital\Laratickets\Contracts\NotificationContract;
use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaraticketsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laratickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_ticket_levels_table')
            ->hasMigration('create_departments_table')
            ->hasMigration('create_tickets_table')
            ->hasMigration('create_ticket_assignments_table')
            ->hasMigration('create_escalation_requests_table')
            ->hasMigration('create_ticket_evaluations_table')
            ->hasMigration('create_agent_ratings_table')
            ->hasMigration('create_risk_assessments_table')
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        // Register contract bindings
        $this->app->bind(
            TicketAuthorizationContract::class,
            fn () => app(config('laratickets.authorization.handler'))
        );

        $this->app->bind(
            UserCapabilityContract::class,
            fn () => app(config('laratickets.authorization.capability_handler'))
        );

        $this->app->bind(
            NotificationContract::class,
            fn () => app(config('laratickets.notifications.handler'))
        );
    }
}
