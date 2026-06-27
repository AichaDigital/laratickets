<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @experimental Orphan event (ADR-004): defined but not emitted by any service
 *               yet — a placeholder for a future SLA watcher. Outside the v1.0
 *               semver promise; safe to remove later.
 */
class SLABreached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket
    ) {}
}
