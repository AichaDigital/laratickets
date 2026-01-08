# Contributing to Laratickets

- **Package:** aichadigital/laratickets
- **Role:** Ticket management system for Larafactu ecosystem


## Migration Pattern - CRITICAL

This package follows the **larabill reference pattern** for migrations.


### Required Pattern

Migrations load automatically via `loadMigrationsFrom()` in the ServiceProvider's `boot()` method:


```php
public function boot(): void
{
    parent::boot();

    if ($this->app->runningInConsole()) {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```



### Migration Files

- MUST be `.php` files (NOT `.stub`)
- MUST have timestamps in filename
- MUST use `MigrationHelper` from larabill for user ID columns


```php
use AichaDigital\Larabill\Support\MigrationHelper;

MigrationHelper::userIdColumn($table, 'user_id');
```



### DO NOT USE

- `hasMigration()` in `configurePackage()` - requires manual publishing
- `.stub` migration files - not auto-loaded
- Direct `$table->foreignId('user_id')` - breaks UUID compatibility


## Historical Note

Prior to 2026-01-08, this package used `.stub` files with `hasMigration()`. This was changed to match larabill's pattern because:

1. Web installer cannot run `vendor:publish`
2. Cross-package dependencies require predictable migration order
3. Timestamp-based ordering ensures correct table creation sequence


## Tables Provided

This package creates the following tables:

- `ticket_levels` - Support tier definitions
- `departments` - Department structure
- `tickets` - Main ticket entities
- `ticket_assignments` - Agent assignments
- `escalation_requests` - Escalation workflow
- `ticket_evaluations` - Customer feedback
- `agent_ratings` - Agent performance
- `risk_assessments` - Risk tracking


## Full Documentation

See: `larafactu/docs/internal/PACKAGE_DEVELOPMENT_STANDARDS.md`


---

*Last updated: 2026-01-08*
