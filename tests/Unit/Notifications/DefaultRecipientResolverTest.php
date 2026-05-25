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
 *   OPENED          → [creator, department recipient]
 *   STAFF_REPLIED   → [creator]
 *   CLIENT_REPLIED  → [active agent] if any, else [department recipient]
 *   CLOSED          → [creator]
 *
 * The "department recipient" is resolved in priority order:
 *   1. `Department.head_user_id` → `Recipient::user($head_user_id)`
 *   2. `Department.mailbox_email` → `Recipient::mailbox($email)`
 *   3. Neither set → `MissingDepartmentMailboxException`
 *
 * Invariant: never returns []; if the department has no head and no
 * mailbox, the exception is thrown rather than silently dropping.
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

    it('routes to the department head when set and no agent is assigned', function () {
        $this->department->update(['head_user_id' => TestCase::USER_UUID_3]);

        $recipients = $this->resolver->resolve($this->ticket->fresh(), TicketEvent::CLIENT_REPLIED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_3);
    });

    it('prefers the head over the mailbox when both are set', function () {
        $this->department->update(['head_user_id' => TestCase::USER_UUID_3]);

        $recipients = $this->resolver->resolve($this->ticket->fresh(), TicketEvent::CLIENT_REPLIED);

        expect($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_3);
    });

    it('falls back to mailbox when head is not set but mailbox is', function () {
        // No head_user_id; mailbox_email already set in beforeEach.
        $recipients = $this->resolver->resolve($this->ticket->fresh(), TicketEvent::CLIENT_REPLIED);

        expect($recipients[0]->isMailbox())->toBeTrue()
            ->and($recipients[0]->email)->toBe('tech@example.test');
    });

    it('throws MissingDepartmentMailboxException when neither head nor mailbox is set', function () {
        $this->department->update(['head_user_id' => null, 'mailbox_email' => null]);

        expect(fn () => $this->resolver->resolve($this->ticket->fresh(), TicketEvent::CLIENT_REPLIED))
            ->toThrow(MissingDepartmentMailboxException::class);
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

    it('routes to the creator and the department head when head is set', function () {
        $this->department->update(['head_user_id' => TestCase::USER_UUID_3]);

        $recipients = $this->resolver->resolve($this->ticket->fresh(), TicketEvent::OPENED);

        expect($recipients)->toHaveCount(2)
            ->and($recipients[0]->isUser())->toBeTrue()
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_1)
            ->and($recipients[1]->isUser())->toBeTrue()
            ->and($recipients[1]->userId)->toBe(TestCase::USER_UUID_3);
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
