<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Seeders;

use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Database\Seeder;

class TicketLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'level' => 1,
                'name' => 'Level I - Basic Support',
                'description' => 'First line support for basic inquiries and common issues',
                'can_escalate' => true,
                'can_assess_risk' => false,
                'default_sla_hours' => 24,
                'active' => true,
            ],
            [
                'level' => 2,
                'name' => 'Level II - Technical Support',
                'description' => 'Advanced technical support for complex issues',
                'can_escalate' => true,
                'can_assess_risk' => false,
                'default_sla_hours' => 48,
                'active' => true,
            ],
            [
                'level' => 3,
                'name' => 'Level III - Expert Support',
                'description' => 'Expert level support with risk assessment capabilities',
                'can_escalate' => true,
                'can_assess_risk' => true,
                'default_sla_hours' => 72,
                'active' => true,
            ],
            [
                'level' => 4,
                'name' => 'Level IV - Management',
                'description' => 'Management level for critical escalations',
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
}
