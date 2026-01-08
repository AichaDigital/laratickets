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
    public function boot(): void
    {
        parent::boot();

        // Load migrations automatically (same pattern as larabill)
        // Migrations use MigrationHelper for ID type agnosticism
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laratickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('api')
            ->hasCommand(InstallCommand::class);

        // Note: Migrations load automatically via loadMigrationsFrom() in boot()
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
