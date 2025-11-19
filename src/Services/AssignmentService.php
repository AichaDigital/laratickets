<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketAssigned;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization,
        protected UserCapabilityContract $userCapability
    ) {}

    public function assignAgent(Ticket $ticket, $agent, $assigner = null): TicketAssignment
    {
        if ($assigner && ! $this->authorization->canUpdateTicket($assigner, $ticket)) {
            throw new \RuntimeException('User is not authorized to assign agents');
        }

        // Verify agent has access to ticket level
        $agentLevel = $this->userCapability->getUserLevel($agent);
        if (! $agentLevel || $agentLevel->level < $ticket->currentLevel->level) {
            throw new \RuntimeException('Agent does not have access to this ticket level');
        }

        // Check max concurrent tickets if configured
        $maxConcurrent = config('laratickets.assignment.max_concurrent_tickets');
        if ($maxConcurrent) {
            $activeTickets = $this->userCapability->getUserAssignedTickets($agent)->count();
            if ($activeTickets >= $maxConcurrent) {
                throw new \RuntimeException('Agent has reached maximum concurrent tickets');
            }
        }

        return DB::transaction(function () use ($ticket, $agent) {
            // Check if already assigned
            $existing = $ticket->activeAssignments()
                ->where('user_id', $agent->{config('laratickets.user.id_column', 'id')})
                ->first();

            if ($existing) {
                return $existing;
            }

            $assignment = TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $agent->{config('laratickets.user.id_column', 'id')},
            ]);

            // Update ticket status if it's new
            if ($ticket->status === TicketStatus::NEW) {
                $ticket->update(['status' => TicketStatus::ASSIGNED]);
            }

            event(new TicketAssigned($ticket, $agent));

            return $assignment;
        });
    }

    public function unassignAgent(Ticket $ticket, $agent): void
    {
        $assignment = $ticket->activeAssignments()
            ->where('user_id', $agent->{config('laratickets.user.id_column', 'id')})
            ->first();

        if (! $assignment) {
            throw new \RuntimeException('Agent is not assigned to this ticket');
        }

        $assignment->complete();
    }

    public function getAvailableAgentsForLevel(TicketLevel $level): Collection
    {
        // This method should be implemented by the application
        // as it depends on the user model and relationships
        return collect([]);
    }

    public function autoAssignByWorkload(Ticket $ticket): ?TicketAssignment
    {
        if (! config('laratickets.assignment.auto_assign_enabled', false)) {
            return null;
        }

        $strategy = config('laratickets.assignment.auto_assign_strategy', 'round_robin');

        return match ($strategy) {
            'round_robin' => $this->assignByRoundRobin($ticket),
            'least_loaded' => $this->assignByLeastLoaded($ticket),
            default => null,
        };
    }

    protected function assignByRoundRobin(Ticket $ticket): ?TicketAssignment
    {
        $availableAgents = $this->getAvailableAgentsForLevel($ticket->currentLevel);

        if ($availableAgents->isEmpty()) {
            return null;
        }

        // Simple round-robin: find agent with least recent assignment
        $agent = $availableAgents->first();

        return $this->assignAgent($ticket, $agent);
    }

    protected function assignByLeastLoaded(Ticket $ticket): ?TicketAssignment
    {
        $availableAgents = $this->getAvailableAgentsForLevel($ticket->currentLevel);

        if ($availableAgents->isEmpty()) {
            return null;
        }

        // Find agent with fewest active tickets
        $agent = $availableAgents->sortBy(function ($agent) {
            return $this->userCapability->getUserAssignedTickets($agent)->count();
        })->first();

        return $this->assignAgent($ticket, $agent);
    }

    public function reassignTicket(Ticket $ticket, $oldAgent, $newAgent, $assigner): TicketAssignment
    {
        if (! $this->authorization->canUpdateTicket($assigner, $ticket)) {
            throw new \RuntimeException('User is not authorized to reassign tickets');
        }

        return DB::transaction(function () use ($ticket, $oldAgent, $newAgent) {
            $this->unassignAgent($ticket, $oldAgent);

            return $this->assignAgent($ticket, $newAgent);
        });
    }
}
