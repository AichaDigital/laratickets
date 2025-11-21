<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Commands;

use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'laratickets:install
                          {--seed : Seed default levels and departments}
                          {--force : Force overwrite existing files}
                          {--no-migrate : Skip running migrations}';

    protected $description = 'Install Laratickets package';

    protected array $migrationOrder = [
        '001' => 'create_ticket_levels_table',
        '002' => 'create_departments_table',
        '003' => 'create_tickets_table',
        '004' => 'create_ticket_assignments_table',
        '005' => 'create_escalation_requests_table',
        '006' => 'create_ticket_evaluations_table',
        '007' => 'create_agent_ratings_table',
        '008' => 'create_risk_assessments_table',
    ];

    public function handle(): int
    {
        $this->info('🚀 Installing Laratickets...');
        $this->newLine();

        // Publish config
        $this->info('📝 Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'laratickets-config',
            '--force' => $this->option('force'),
        ]);
        $this->info('✓ Configuration published');

        // Publish migrations IN ORDER
        $this->info('📄 Publishing migrations...');
        $published = $this->publishMigrationsInOrder();

        if ($published === 0) {
            $this->comment('⚠ No new migrations to publish (use --force to overwrite)');
        }

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

    protected function publishMigrationsInOrder(): int
    {
        $packagePath = dirname(__DIR__, 2).'/database/migrations';
        $targetPath = database_path('migrations');
        $timestamp = now();

        $published = 0;

        foreach ($this->migrationOrder as $order => $migrationName) {
            $stubFiles = [
                "{$packagePath}/{$migrationName}.php.stub",
                "{$packagePath}/{$migrationName}.php",
            ];

            foreach (File::glob("{$packagePath}/????_??_??_??????_{$migrationName}.php*") as $file) {
                $stubFiles[] = $file;
            }

            $stubFile = null;
            foreach ($stubFiles as $file) {
                if (File::exists($file)) {
                    $stubFile = $file;
                    break;
                }
            }

            if (! $stubFile) {
                $this->warn("⚠ Migration stub not found: {$migrationName}");

                continue;
            }

            $migrationTimestamp = $timestamp->copy()->addSeconds((int) $order);
            $targetFile = $targetPath.'/'.$migrationTimestamp->format('Y_m_d_His').'_'.$migrationName.'.php';

            if (File::exists($targetFile) && ! $this->option('force')) {
                continue;
            }

            File::copy($stubFile, $targetFile);
            $published++;
        }

        $this->info("✓ Published {$published} migrations");

        return $published;
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
