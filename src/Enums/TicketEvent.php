<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

/**
 * Routable ticket events — the dimension on which the Core resolves recipients.
 *
 * Only the events whose recipient routing is implemented appear here. Adding a
 * new case obliges adding its arm to `DefaultRecipientResolver::resolve()`
 * (the `match` is exhaustive). Events for flows not yet routed by the Core
 * (assignment, escalation, redaction) will be added with their flow.
 */
enum TicketEvent: string
{
    case OPENED = 'opened';
    case STAFF_REPLIED = 'staff_replied';
    case CLIENT_REPLIED = 'client_replied';
    case CLOSED = 'closed';
}
