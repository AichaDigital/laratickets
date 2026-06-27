<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketCancelled;
use AichaDigital\Laratickets\Events\TicketClosed;
use AichaDigital\Laratickets\Events\TicketCreated;
use AichaDigital\Laratickets\Events\TicketResolved;
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
     * Update ticket status.
     *
     * D1 (ADR-004): terminal targets delegate to the dedicated apply* methods
     * so they emit their specific event (and set the right metadata) — never
     * TicketStatusChanged. This keeps the anti-duplicate invariant with a single
     * entry point and is transparent to callers that close via this generic path.
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function updateTicketStatus(Ticket $ticket, TicketStatus $newStatus, $by): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to update this ticket');
        }

        if ($ticket->status === $newStatus) {
            return $ticket;
        }

        return match ($newStatus) {
            TicketStatus::RESOLVED => $this->applyResolve($ticket, $by),
            TicketStatus::CLOSED => $this->applyClose($ticket, $by),
            TicketStatus::CANCELLED => $this->applyCancel($ticket, $by),
            default => $this->applyStatusChange($ticket, $newStatus),
        };
    }

    /**
     * Close a ticket.
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function closeTicket(Ticket $ticket, $by): Ticket
    {
        if (! $this->authorization->canCloseTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to close this ticket');
        }

        return $this->applyClose($ticket, $by);
    }

    /**
     * Resolve a ticket.
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function resolveTicket(Ticket $ticket, $by): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to resolve this ticket');
        }

        return $this->applyResolve($ticket, $by);
    }

    /**
     * Cancel a ticket.
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function cancelTicket(Ticket $ticket, $by, ?string $reason = null): Ticket
    {
        if (! $this->authorization->canUpdateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to cancel this ticket');
        }

        return $this->applyCancel($ticket, $by);
    }

    /**
     * Non-terminal status change: the only path that emits TicketStatusChanged.
     */
    private function applyStatusChange(Ticket $ticket, TicketStatus $newStatus): Ticket
    {
        $oldStatus = $ticket->status;

        $ticket->update(['status' => $newStatus]);

        event(new TicketStatusChanged($ticket, $oldStatus, $newStatus));

        return $ticket->fresh();
    }

    /**
     * @param  mixed  $by
     */
    private function applyResolve(Ticket $ticket, $by): Ticket
    {
        $ticket->update([
            'status' => TicketStatus::RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => ActorId::of($by),
        ]);

        event(new TicketResolved($ticket));

        return $ticket->fresh();
    }

    /**
     * @param  mixed  $by
     */
    private function applyClose(Ticket $ticket, $by): Ticket
    {
        return DB::transaction(function () use ($ticket, $by) {
            $ticket->update([
                'status' => TicketStatus::CLOSED,
                'closed_at' => now(),
                'resolved_by' => ActorId::of($by),
            ]);

            $ticket->activeAssignments()->update(['completed_at' => now()]);

            event(new TicketClosed($ticket));

            return $ticket->fresh();
        });
    }

    /**
     * @param  mixed  $by
     */
    private function applyCancel(Ticket $ticket, $by): Ticket
    {
        return DB::transaction(function () use ($ticket, $by) {
            $ticket->update([
                'status' => TicketStatus::CANCELLED,
                'closed_at' => now(),
                'resolved_by' => ActorId::of($by),
            ]);

            $ticket->activeAssignments()->update(['completed_at' => now()]);

            event(new TicketCancelled($ticket));

            return $ticket->fresh();
        });
    }

    public function updateEstimatedDeadline(Ticket $ticket, \DateTimeInterface $deadline): Ticket
    {
        $ticket->update(['estimated_deadline' => $deadline]);

        return $ticket->fresh();
    }
}
