<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketClosed;
use AichaDigital\Laratickets\Events\TicketCreated;
use AichaDigital\Laratickets\Events\TicketStatusChanged;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Support\ActorId;
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
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function createTicket(array $data, $by): Ticket
    {
        if (! $this->authorization->canCreateTicket($by)) {
            throw new TicketAuthorizationException('User is not authorized to create tickets');
        }

        return DB::transaction(function () use ($data, $by) {
            $levelOne = TicketLevel::where('level', 1)->firstOrFail();

            $ticket = Ticket::create([
                'subject' => $data['subject'],
                'description' => $data['description'],
                'user_priority' => $data['priority'] ?? 'medium',
                'department_id' => $data['department_id'],
                'current_level_id' => $levelOne->id,
                'created_by' => ActorId::of($by),
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
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function updateTicketStatus(Ticket $ticket, TicketStatus $newStatus, $by): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to update this ticket');
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
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function closeTicket(Ticket $ticket, $by): Ticket
    {
        if (! $this->authorization->canCloseTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to close this ticket');
        }

        return DB::transaction(function () use ($ticket, $by) {
            $ticket->update([
                'status' => TicketStatus::CLOSED,
                'closed_at' => now(),
                'resolved_by' => ActorId::of($by),
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
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function resolveTicket(Ticket $ticket, $by): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to resolve this ticket');
        }

        $ticket->update([
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => ActorId::of($by),
        ]);

        return $ticket->fresh();
    }

    /**
     * Cancel a ticket
     *
     * Uses database transaction for atomicity.
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function cancelTicket(Ticket $ticket, $by, ?string $reason = null): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to cancel this ticket');
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
