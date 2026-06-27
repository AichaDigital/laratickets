<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\TicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket message was redacted, leaving an auditable trail (ADR-004 v1.0
 * matrix; closes the open decision #2 of the v0.6.0 wishlist).
 */
class TicketMessageRedacted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TicketMessage $message
    ) {}
}
