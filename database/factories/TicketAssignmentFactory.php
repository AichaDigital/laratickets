<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketAssignment>
 */
class TicketAssignmentFactory extends Factory
{
    protected $model = TicketAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => (string) Str::uuid7(),
            'assigned_at' => now(),
            'completed_at' => null,
            'individual_rating' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
            'individual_rating' => fake()->randomFloat(2, 1, 5),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['completed_at' => null]);
    }
}
