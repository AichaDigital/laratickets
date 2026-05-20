<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\TicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a ticket message is persisted (v0.5.0).
 */
class TicketMessagePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public TicketMessage $message) {}
}
