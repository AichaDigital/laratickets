<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EscalationRequest>
 */
class EscalationRequestFactory extends Factory
{
    protected $model = EscalationRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'from_level_id' => TicketLevel::factory(),
            'to_level_id' => TicketLevel::factory(),
            'requester_id' => (string) Str::uuid7(),
            'approver_id' => null,
            'justification' => fake()->sentence(),
            'status' => 'pending',
            'rejection_reason' => null,
            'is_automatic' => false,
            'requested_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'approver_id' => (string) Str::uuid7(),
            'rejection_reason' => null,
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'approver_id' => (string) Str::uuid7(),
            'rejection_reason' => fake()->sentence(),
            'resolved_at' => now(),
        ]);
    }

    public function automatic(): static
    {
        // System/SLA auto-escalations have no human requester; the migration
        // made requester_id nullable for exactly this (resolved from SystemActor).
        return $this->state(fn (): array => [
            'is_automatic' => true,
            'requester_id' => null,
        ]);
    }
}
