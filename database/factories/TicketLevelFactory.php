<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketLevel>
 */
class TicketLevelFactory extends Factory
{
    protected $model = TicketLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `level` is UNIQUE and 1-4 are seeded in production, so the default uses
        // a monotonic counter above that range. A plain counter avoids both a
        // collision with seeded levels and Faker unique()'s per-process state
        // that survives RefreshDatabase. Tests needing a specific order pass an
        // explicit level.
        return [
            'level' => $this->nextLevel(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'can_escalate' => true,
            'can_assess_risk' => false,
            'default_sla_hours' => 24,
            'active' => true,
        ];
    }

    /**
     * Monotonic, process-unique level above the seeded 1-4 production range.
     */
    private function nextLevel(): int
    {
        static $next = 1000;

        return $next++;
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function canAssessRisk(): static
    {
        return $this->state(fn (): array => ['can_assess_risk' => true]);
    }

    public function terminal(): static
    {
        return $this->state(fn (): array => ['can_escalate' => false]);
    }
}
