<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

enum TicketStatus: string
{
    case NEW = 'new';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case ESCALATION_REQUESTED = 'escalation_requested';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::ASSIGNED => 'Assigned',
            self::IN_PROGRESS => 'In Progress',
            self::ESCALATION_REQUESTED => 'Escalation Requested',
            self::ESCALATED => 'Escalated',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::NEW,
            self::ASSIGNED,
            self::IN_PROGRESS,
            self::ESCALATION_REQUESTED,
            self::ESCALATED,
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this, [
            self::RESOLVED,
            self::CLOSED,
            self::CANCELLED,
        ]);
    }
}
