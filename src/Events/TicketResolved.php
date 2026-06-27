<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket transitioned to RESOLVED. Core, stable (ADR-004 v1.0 matrix).
 */
class TicketResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket
    ) {}
}
