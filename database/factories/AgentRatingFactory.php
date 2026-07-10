<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\AgentRating;
use AichaDigital\Laratickets\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentRating>
 */
class AgentRatingFactory extends Factory
{
    protected $model = AgentRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'agent_id' => (string) Str::uuid7(),
            'rater_id' => (string) Str::uuid7(),
            'score' => fake()->randomFloat(2, 1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }

    public function highRated(): static
    {
        return $this->state(fn (): array => ['score' => fake()->randomFloat(2, 4, 5)]);
    }

    public function lowRated(): static
    {
        return $this->state(fn (): array => ['score' => fake()->randomFloat(2, 1, 2)]);
    }
}
