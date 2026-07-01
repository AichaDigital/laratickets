<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Commands;

use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'laratickets:install
                          {--seed : Seed default levels and departments}
                          {--force : Force overwrite existing files}
                          {--no-migrate : Skip running migrations}
                          {--skip-uuid-check : Skip the users.id UUID preflight check}';

    protected $description = 'Install Laratickets package';

    public function handle(): int
    {
        $this->info('🚀 Installing Laratickets...');
        $this->newLine();

        if (! $this->option('skip-uuid-check') && ! $this->verifyUsersTableUuid()) {
            return self::FAILURE;
        }

        // Publish config
        $this->info('📝 Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'laratickets-config',
            '--force' => $this->option('force'),
        ]);
        $this->info('✓ Configuration published');

        // Migrations are package-managed (loaded via loadMigrationsFrom in the
        // ServiceProvider); the install command does not publish them. See ADR-005.

        // Run migrations if not --no-migrate
        if (! $this->option('no-migrate')) {
            if ($this->confirm('Run migrations now?', true)) {
                $this->info('🔄 Running migrations...');
                $this->call('migrate');
                $this->info('✓ Migrations completed');
            }
        }

        // Seed data if requested
        if ($this->option('seed')) {
            $this->seedData();
        }

        $this->newLine();
        $this->info('✅ Laratickets installed successfully!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  - Configure your user model in config/laratickets.php');
        $this->line('  - Implement authorization contracts if needed');
        $this->line('  - Seed data: php artisan laratickets:install --seed');

        return self::SUCCESS;
    }

    /**
     * Preflight: verify that `users.id` is UUID char(36).
     *
     * Per ADR-001 (laratickets) and ADR-006 (larabill canonical), laratickets
     * is UUID-first. The 9 FK columns to users.id (created_by, resolved_by,
     * user_id, requester_id, approver_id, evaluator_id, agent_id, rater_id,
     * assessor_id) are emitted as char(36) and require a matching users.id.
     *
     * If the consumer's users.id is bigInteger or ULID, FK migrations would
     * succeed but later inserts/joins would fail at runtime. This preflight
     * aborts install before any damage is done.
     *
     * Pass --skip-uuid-check to bypass (e.g. CI dry-runs, custom workflows).
     */
    protected function verifyUsersTableUuid(): bool
    {
        $idColumn = (string) config('laratickets.user.id_column', 'id');

        if (! Schema::hasTable('users')) {
            $this->warn(sprintf(
                '⚠ Users table not found — skipping preflight. Ensure your `users.%s` column is char(36) UUID v7 before migrating.',
                $idColumn
            ));
            $this->line('  See: https://github.com/AichaDigital/larabill/blob/main/docs/setup-uuid.md');

            return true;
        }

        $detectedType = $this->detectUsersIdType($idColumn);

        if ($detectedType === 'uuid') {
            $this->info(sprintf('✓ Preflight OK — users.%s is UUID char(36)', $idColumn));

            return true;
        }

        $this->error(sprintf(
            '✗ Preflight failed — users.%s is not UUID char(36) (detected: %s).',
            $idColumn,
            $detectedType ?? 'unknown'
        ));
        $this->line('');
        $this->line('Laratickets requires the consumer app to use UUID v7 char(36) for users.id.');
        $this->line('See setup guide: https://github.com/AichaDigital/larabill/blob/main/docs/setup-uuid.md');
        $this->line('');
        $this->line('Pass --skip-uuid-check to bypass this check (NOT recommended).');

        return false;
    }

    protected function detectUsersIdType(string $idColumn): ?string
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $column = DB::selectOne(
                    'SHOW COLUMNS FROM users WHERE Field = ?',
                    [$idColumn]
                );

                if (! $column) {
                    return null;
                }

                $type = strtolower($column->Type);

                if (str_contains($type, 'char(36)') || str_contains($type, 'varchar(36)') || $type === 'uuid') {
                    return 'uuid';
                }

                if (str_contains($type, 'bigint') || str_contains($type, 'int(')) {
                    return 'integer';
                }

                if (str_contains($type, 'char(26)') || str_contains($type, 'varchar(26)')) {
                    return 'ulid';
                }

                return $type;
            }

            if ($driver === 'pgsql') {
                $column = DB::selectOne(
                    "SELECT data_type, character_maximum_length
                     FROM information_schema.columns
                     WHERE table_name = 'users' AND column_name = ?",
                    [$idColumn]
                );

                if (! $column) {
                    return null;
                }

                $type = strtolower($column->data_type);

                if ($type === 'uuid') {
                    return 'uuid';
                }

                if (in_array($type, ['character', 'character varying'], true)) {
                    if ((int) $column->character_maximum_length === 36) {
                        return 'uuid';
                    }
                    if ((int) $column->character_maximum_length === 26) {
                        return 'ulid';
                    }
                }

                if (in_array($type, ['bigint', 'integer'], true)) {
                    return 'integer';
                }

                return $type;
            }

            if ($driver === 'sqlite') {
                $sample = DB::table('users')->select($idColumn)->first();
                if (! $sample) {
                    return 'uuid';
                }

                $value = $sample->{$idColumn} ?? null;

                if (is_int($value)) {
                    return 'integer';
                }

                if (is_string($value)) {
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1) {
                        return 'uuid';
                    }
                    if (strlen($value) === 26) {
                        return 'ulid';
                    }
                }

                return 'unknown';
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function seedData(): void
    {
        $force = $this->option('force');

        // Seed ticket levels
        if ($force || TicketLevel::count() === 0) {
            $this->info('Seeding ticket levels...');
            $this->seedLevels();
            $this->info('✓ Ticket levels seeded');
        } else {
            $this->warn('Ticket levels already exist. Use --force to reseed.');
        }

        // Seed departments
        if ($force || Department::count() === 0) {
            $this->info('Seeding departments...');
            $this->seedDepartments();
            $this->info('✓ Departments seeded');
        } else {
            $this->warn('Departments already exist. Use --force to reseed.');
        }
    }

    protected function seedLevels(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'Level I',
                'description' => 'Basic support level for initial ticket triage',
                'can_escalate' => true,
                'can_assess_risk' => false,
                'default_sla_hours' => 24,
                'active' => true,
            ],
            [
                'level' => 2,
                'name' => 'Level II',
                'description' => 'Intermediate support level for complex issues',
                'can_escalate' => true,
                'can_assess_risk' => false,
                'default_sla_hours' => 48,
                'active' => true,
            ],
            [
                'level' => 3,
                'name' => 'Level III',
                'description' => 'Advanced support level with risk assessment capabilities',
                'can_escalate' => true,
                'can_assess_risk' => true,
                'default_sla_hours' => 72,
                'active' => true,
            ],
            [
                'level' => 4,
                'name' => 'Level IV',
                'description' => 'Expert support level for critical issues',
                'can_escalate' => false,
                'can_assess_risk' => true,
                'default_sla_hours' => 96,
                'active' => true,
            ],
        ];

        foreach ($levels as $levelData) {
            TicketLevel::updateOrCreate(
                ['level' => $levelData['level']],
                $levelData
            );
        }
    }

    protected function seedDepartments(): void
    {
        $departments = config('laratickets.departments.default', [
            ['name' => 'Technical', 'description' => 'Technical support department'],
            ['name' => 'Administrative', 'description' => 'Administrative support department'],
            ['name' => 'Commercial', 'description' => 'Commercial support department'],
        ]);

        foreach ($departments as $deptData) {
            Department::firstOrCreate(
                ['name' => $deptData['name']],
                [
                    'description' => $deptData['description'] ?? null,
                    'active' => true,
                ]
            );
        }
    }
}
