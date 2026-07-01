<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * AID-290 / ADR-005 — laratickets migrations are package-managed.
 *
 * The schema is loaded from the package via loadMigrationsFrom(); the install
 * command must NOT copy migrations into the consumer app. Publishing was the
 * broken, redundant path (partial $migrationOrder + a second schema origin that
 * collides with auto-load). This test pins the package-managed contract.
 */
describe('laratickets:install (package-managed migrations)', function () {
    it('does not publish migrations into the application', function () {
        $migrationsPath = database_path('migrations');
        File::ensureDirectoryExists($migrationsPath);

        $before = collect(File::files($migrationsPath))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        $this->artisan('laratickets:install', [
            '--no-migrate' => true,
            '--skip-uuid-check' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $after = collect(File::files($migrationsPath))
            ->map(fn ($file) => $file->getFilename())
            ->all();

        $published = array_values(array_diff($after, $before));

        // Leave no residue regardless of outcome.
        foreach ($published as $name) {
            File::delete($migrationsPath.'/'.$name);
        }

        expect($published)->toBe([]);
    });
});
