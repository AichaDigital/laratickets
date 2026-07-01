<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets;

use AichaDigital\Laratickets\Commands\InstallCommand;
use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Notifications\RecipientResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaraticketsServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Migrations are package-managed: laratickets owns its schema and loads
        // it from the package (no publishing). loadMigrationsFrom runs in every
        // console context — production included — so the consumer's `migrate`
        // discovers the package schema. Do NOT add a `! environment('production')`
        // guard here: that guard only applies to packages that publish stubs
        // (e.g. larabill). See ADR-005. User FKs are UUID v7 char(36) via
        // MigrationHelper::userIdColumn() (STD-001).
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
            RecipientResolver::class,
            fn () => app(config('laratickets.notifications.recipient_resolver'))
        );
    }
}
