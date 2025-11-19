<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Commands;

use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'laratickets:install
                          {--seed : Seed default levels and departments}
                          {--force : Force seed even if data exists}';

    protected $description = 'Install Laratickets package';

    public function handle(): int
    {
        $this->info('Installing Laratickets...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'laratickets-config',
            '--force' => true,
        ]);

        $this->info('✓ Configuration published');

        // Run migrations
        $this->info('Running migrations...');
        $this->call('migrate');
        $this->info('✓ Migrations completed');

        // Seed data if requested
        if ($this->option('seed')) {
            $this->seedData();
        }

        $this->newLine();
        $this->info('Laratickets installed successfully!');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('1. Configure your user model in config/laratickets.php');
        $this->line('2. Implement authorization contracts if needed');
        $this->line('3. Run php artisan laratickets:seed to seed default data');
        $this->newLine();

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
