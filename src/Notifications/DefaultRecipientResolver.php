<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Notifications;

use AichaDigital\Laratickets\Enums\TicketEvent;
use AichaDigital\Laratickets\Exceptions\MissingDepartmentMailboxException;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;

/**
 * Default `RecipientResolver` shipped with the package.
 *
 *   OPENED          → [creator, mailbox]
 *   STAFF_REPLIED   → [creator]
 *   CLIENT_REPLIED  → [active agent] if any, else [mailbox]
 *   CLOSED          → [creator]
 *
 * Reads current state (not history) for active assignments — an
 * assigned→unassigned ticket falls back to the mailbox the same way as a
 * never-assigned one.
 */
final class DefaultRecipientResolver implements RecipientResolver
{
    public function resolve(Ticket $ticket, TicketEvent $event): array
    {
        return match ($event) {
            TicketEvent::OPENED => [
                $this->creator($ticket),
                $this->departmentMailbox($ticket),
            ],
            TicketEvent::STAFF_REPLIED, TicketEvent::CLOSED => [
                $this->creator($ticket),
            ],
            TicketEvent::CLIENT_REPLIED => [
                $this->activeAgent($ticket) ?? $this->departmentMailbox($ticket),
            ],
        };
    }

    private function creator(Ticket $ticket): Recipient
    {
        return Recipient::user((string) $ticket->created_by);
    }

    private function activeAgent(Ticket $ticket): ?Recipient
    {
        /** @var TicketAssignment|null $assignment */
        $assignment = $ticket->activeAssignments()->first();

        if ($assignment === null) {
            return null;
        }

        return Recipient::user((string) $assignment->user_id);
    }

    private function departmentMailbox(Ticket $ticket): Recipient
    {
        $department = $ticket->department;
        $email = $department->mailbox_email;

        if ($email === null || $email === '') {
            throw MissingDepartmentMailboxException::for($department);
        }

        return Recipient::mailbox($email);
    }
}
