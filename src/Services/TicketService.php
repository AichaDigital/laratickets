<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketClosed;
use AichaDigital\Laratickets\Events\TicketCreated;
use AichaDigital\Laratickets\Events\TicketStatusChanged;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    /**
     * Create a new ticket
     *
     * @param  array<string, mixed>  $data
     * @param  mixed  $creator  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function createTicket(array $data, $creator): Ticket
    {
        if (! $this->authorization->canCreateTicket($creator)) {
            throw new \RuntimeException('User is not authorized to create tickets');
        }

        return DB::transaction(function () use ($data, $creator) {
            $levelOne = TicketLevel::where('level', 1)->firstOrFail();

            $ticket = Ticket::create([
                'subject' => $data['subject'],
                'description' => $data['description'],
                'user_priority' => $data['priority'] ?? 'medium',
                'department_id' => $data['department_id'],
                'current_level_id' => $levelOne->id,
                'created_by' => $creator->{config('laratickets.user.id_column', 'id')},
                'status' => TicketStatus::NEW,
                'estimated_deadline' => now()->addHours($levelOne->default_sla_hours),
            ]);

            event(new TicketCreated($ticket));

            return $ticket->fresh(['currentLevel', 'department']);
        });
    }

    /**
     * Update ticket status
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function updateTicketStatus(Ticket $ticket, TicketStatus $newStatus, $user): Ticket
    {
        if (! $this->authorization->canUpdateTicket($user, $ticket)) {
            throw new \RuntimeException('User is not authorized to update this ticket');
        }

        $oldStatus = $ticket->status;

        if ($oldStatus === $newStatus) {
            return $ticket;
        }

        $ticket->update(['status' => $newStatus]);

        event(new TicketStatusChanged($ticket, $oldStatus, $newStatus));

        return $ticket->fresh();
    }

    /**
     * Close a ticket
     *
     * @param  mixed  $resolver  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function closeTicket(Ticket $ticket, $resolver): Ticket
    {
        if (! $this->authorization->canCloseTicket($resolver, $ticket)) {
            throw new \RuntimeException('User is not authorized to close this ticket');
        }

        return DB::transaction(function () use ($ticket, $resolver) {
            $ticket->update([
                'status' => TicketStatus::CLOSED,
                'closed_at' => now(),
                'resolved_by' => $resolver->{config('laratickets.user.id_column', 'id')},
            ]);

            // Complete all active assignments
            $ticket->activeAssignments()->update(['completed_at' => now()]);

            event(new TicketClosed($ticket));

            return $ticket->fresh();
        });
    }

    /**
     * Resolve a ticket
     *
     * @param  mixed  $resolver  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function resolveTicket(Ticket $ticket, $resolver): Ticket
    {
        if (! $this->authorization->canUpdateTicket($resolver, $ticket)) {
            throw new \RuntimeException('User is not authorized to resolve this ticket');
        }

        $ticket->update([
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $resolver->{config('laratickets.user.id_column', 'id')},
        ]);

        return $ticket->fresh();
    }

    /**
     * Cancel a ticket
     *
     * Uses database transaction for atomicity.
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function cancelTicket(Ticket $ticket, $user, ?string $reason = null): Ticket
    {
        if (! $this->authorization->canUpdateTicket($user, $ticket)) {
            throw new \RuntimeException('User is not authorized to cancel this ticket');
        }

        return DB::transaction(function () use ($ticket) {
            $ticket->update([
                'status' => TicketStatus::CANCELLED,
                'closed_at' => now(),
            ]);

            // Complete all active assignments
            $ticket->activeAssignments()->update(['completed_at' => now()]);

            return $ticket->fresh();
        });
    }

    public function updateEstimatedDeadline(Ticket $ticket, \DateTimeInterface $deadline): Ticket
    {
        $ticket->update(['estimated_deadline' => $deadline]);

        return $ticket->fresh();
    }
}
