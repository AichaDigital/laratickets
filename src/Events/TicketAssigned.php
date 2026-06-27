<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned
{
    use Dispatchable, SerializesModels;

    /**
     * @param  mixed  $agent  The agent assigned (or reassigned) to the ticket.
     * @param  mixed  $assignedBy  The actor performing the assignment, or null
     *                             for system/auto assignment. v1.0 (ADR-004)
     *                             renamed the former `$user` to `$agent` and
     *                             added the assigner.
     */
    public function __construct(
        public Ticket $ticket,
        public mixed $agent,
        public mixed $assignedBy = null
    ) {}
}
