<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketEvaluation>
 */
class TicketEvaluationFactory extends Factory
{
    protected $model = TicketEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'evaluator_id' => (string) Str::uuid7(),
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
