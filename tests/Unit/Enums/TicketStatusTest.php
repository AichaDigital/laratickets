<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\TicketStatus;

it('has correct status values', function () {
    expect(TicketStatus::NEW->value)->toBe('new')
        ->and(TicketStatus::ASSIGNED->value)->toBe('assigned')
        ->and(TicketStatus::IN_PROGRESS->value)->toBe('in_progress')
        ->and(TicketStatus::ESCALATION_REQUESTED->value)->toBe('escalation_requested')
        ->and(TicketStatus::ESCALATED->value)->toBe('escalated')
        ->and(TicketStatus::RESOLVED->value)->toBe('resolved')
        ->and(TicketStatus::CLOSED->value)->toBe('closed')
        ->and(TicketStatus::CANCELLED->value)->toBe('cancelled');
});

it('returns correct labels', function () {
    expect(TicketStatus::NEW->label())->toBe('New')
        ->and(TicketStatus::IN_PROGRESS->label())->toBe('In Progress')
        ->and(TicketStatus::CLOSED->label())->toBe('Closed');
});

it('can determine if status is open', function () {
    expect(TicketStatus::NEW->isOpen())->toBeTrue()
        ->and(TicketStatus::IN_PROGRESS->isOpen())->toBeTrue()
        ->and(TicketStatus::ESCALATED->isOpen())->toBeTrue()
        ->and(TicketStatus::CLOSED->isOpen())->toBeFalse()
        ->and(TicketStatus::CANCELLED->isOpen())->toBeFalse();
});

it('can determine if status is closed', function () {
    expect(TicketStatus::CLOSED->isClosed())->toBeTrue()
        ->and(TicketStatus::RESOLVED->isClosed())->toBeTrue()
        ->and(TicketStatus::CANCELLED->isClosed())->toBeTrue()
        ->and(TicketStatus::NEW->isClosed())->toBeFalse()
        ->and(TicketStatus::IN_PROGRESS->isClosed())->toBeFalse();
});
