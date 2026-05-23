<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Notifications;

use AichaDigital\Laratickets\Enums\TicketEvent;
use AichaDigital\Laratickets\Exceptions\MissingDepartmentMailboxException;
use AichaDigital\Laratickets\Models\Ticket;

/**
 * Resolves the recipients that must be notified for a routable ticket event.
 *
 * The consumer may swap the default implementation via
 * `config('laratickets.notifications.recipient_resolver')`.
 */
interface RecipientResolver
{
    /**
     * @return list<Recipient> never empty for a routable event — the resolver
     *                         throws instead of returning `[]`
     *
     * @throws MissingDepartmentMailboxException when a department mailbox is
     *                                           required by the routing rule
     *                                           but the department has none
     *                                           configured
     */
    public function resolve(Ticket $ticket, TicketEvent $event): array;
}
