<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // created_by is a bare UUID column (no FK to the consumer's users table),
        // so a generated UUID v7 is a valid actor id without a User model.
        return [
            'subject' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::NEW,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => TicketLevel::factory(),
            'department_id' => Department::factory(),
            'created_by' => (string) Str::uuid7(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::RESOLVED,
            'resolved_by' => (string) Str::uuid7(),
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        // Mirrors TicketService::applyClose(): CLOSED + closed_at + resolved_by
        // (no resolved_at — a close is not a resolve).
        return $this->state(fn (): array => [
            'status' => TicketStatus::CLOSED,
            'closed_at' => now(),
            'resolved_by' => (string) Str::uuid7(),
        ]);
    }

    public function priority(Priority $priority): static
    {
        return $this->state(fn (): array => ['user_priority' => $priority]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(fn (): array => ['department_id' => $department->id]);
    }

    public function atLevel(TicketLevel $level): static
    {
        return $this->state(fn (): array => ['current_level_id' => $level->id]);
    }
}
