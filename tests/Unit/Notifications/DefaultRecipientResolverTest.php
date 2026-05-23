<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketEvent;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Exceptions\MissingDepartmentMailboxException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Notifications\DefaultRecipientResolver;
use AichaDigital\Laratickets\Notifications\Recipient;
use AichaDigital\Laratickets\Tests\TestCase;

/**
 * Routing matrix for the four in-scope `TicketEvent`s:
 *   OPENED          → [creator, mailbox]
 *   STAFF_REPLIED   → [creator]
 *   CLIENT_REPLIED  → [active agent] if any, else [mailbox]
 *   CLOSED          → [creator]
 *
 * Invariant: never returns []; if a mailbox is required but missing,
 * `MissingDepartmentMailboxException` is thrown.
 */
beforeEach(function () {
    $this->resolver = new DefaultRecipientResolver;

    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create([
        'name' => 'Technical',
        'mailbox_email' => 'tech@example.test',
        'active' => true,
    ]);

    $this->ticket = Ticket::create([
        'subject' => 'subject',
        'description' => 'body',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);
});

describe('STAFF_REPLIED', function () {
    it('routes only to the ticket creator', function () {
        $recipients = $this->resolver->resolve($this->ticket, TicketEvent::STAFF_REPLIED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0])->toBeInstanceOf(Recipient::class)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_1);
    });
});

describe('CLIENT_REPLIED', function () {
    it('routes to the active agent when one is assigned', function () {
        TicketAssignment::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => TestCase::USER_UUID_2,
            'assigned_at' => now(),
            'completed_at' => null,
        ]);

        $recipients = $this->resolver->resolve($this->ticket, TicketEvent::CLIENT_REPLIED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_2);
    });

    it('falls back to the department mailbox when the only assignment is completed (assigned→unassigned gap)', function () {
        // This is the exact hole that blocked session 3: an agent was assigned
        // then removed (completed_at set), and the client replies after that.
        TicketAssignment::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => TestCase::USER_UUID_2,
            'assigned_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $recipients = $this->resolver->resolve($this->ticket, TicketEvent::CLIENT_REPLIED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0]->isMailbox())->toBeTrue()
            ->and($recipients[0]->email)->toBe('tech@example.test');
    });

    it('throws MissingDepartmentMailboxException when fallback is required but the department has no mailbox', function () {
        $this->department->update(['mailbox_email' => null]);

        expect(fn () => $this->resolver->resolve($this->ticket->fresh(), TicketEvent::CLIENT_REPLIED))
            ->toThrow(MissingDepartmentMailboxException::class, 'mailbox_email');
    });
});

describe('OPENED', function () {
    it('routes to the creator and the department mailbox', function () {
        $recipients = $this->resolver->resolve($this->ticket, TicketEvent::OPENED);

        expect($recipients)->toHaveCount(2)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_1)
            ->and($recipients[1]->isMailbox())->toBeTrue()
            ->and($recipients[1]->email)->toBe('tech@example.test');
    });
});

describe('CLOSED', function () {
    it('routes only to the ticket creator', function () {
        $recipients = $this->resolver->resolve($this->ticket, TicketEvent::CLOSED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_1);
    });
});
