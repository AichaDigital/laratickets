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
 *   OPENED          → [creator, department recipient]
 *   STAFF_REPLIED   → [creator]
 *   CLIENT_REPLIED  → [active agent] if any, else [department recipient]
 *   CLOSED          → [creator]
 *
 * The "department recipient" resolves in priority order:
 *   1. `Department.head_user_id` → `Recipient::user($head_user_id)`
 *   2. `Department.mailbox_email` → `Recipient::mailbox($email)`
 *   3. Neither set → `MissingDepartmentMailboxException`
 *
 * Reads current state (not history) for active assignments — an
 * assigned→unassigned ticket falls back to the department the same way as a
 * never-assigned one.
 */
final class DefaultRecipientResolver implements RecipientResolver
{
    public function resolve(Ticket $ticket, TicketEvent $event): array
    {
        return match ($event) {
            TicketEvent::OPENED => [
                $this->creator($ticket),
                $this->departmentRecipient($ticket),
            ],
            TicketEvent::STAFF_REPLIED, TicketEvent::CLOSED => [
                $this->creator($ticket),
            ],
            TicketEvent::CLIENT_REPLIED => [
                $this->activeAgent($ticket) ?? $this->departmentRecipient($ticket),
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

    private function departmentRecipient(Ticket $ticket): Recipient
    {
        $department = $ticket->department;

        $headUserId = $department->head_user_id;
        if ($headUserId !== null && $headUserId !== '') {
            return Recipient::user($headUserId);
        }

        $email = $department->mailbox_email;
        if ($email !== null && $email !== '') {
            return Recipient::mailbox($email);
        }

        throw MissingDepartmentMailboxException::for($department);
    }
}
