<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketAssigned;
use AichaDigital\Laratickets\Events\TicketCancelled;
use AichaDigital\Laratickets\Events\TicketClosed;
use AichaDigital\Laratickets\Events\TicketCreated;
use AichaDigital\Laratickets\Events\TicketMessagePosted;
use AichaDigital\Laratickets\Events\TicketMessageRedacted;
use AichaDigital\Laratickets\Events\TicketResolved;
use AichaDigital\Laratickets\Events\TicketStatusChanged;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Models\TicketMessage;
use AichaDigital\Laratickets\Services\AssignmentService;
use AichaDigital\Laratickets\Services\TicketMessageService;
use AichaDigital\Laratickets\Services\TicketService;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Support\Facades\Event;

/**
 * v1.0 domain-event contract (ADR-004): each domain transition emits exactly
 * ONE event. Terminal transitions (resolved/closed/cancelled) emit their
 * specific event and NEVER also emit TicketStatusChanged, so a listener
 * subscribed to both does not fire twice on a single close.
 */
beforeEach(function () {
    Event::fake([
        TicketCreated::class,
        TicketAssigned::class,
        TicketStatusChanged::class,
        TicketResolved::class,
        TicketClosed::class,
        TicketCancelled::class,
        TicketMessagePosted::class,
        TicketMessageRedacted::class,
    ]);

    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->actor = new class
    {
        public string $id = TestCase::USER_UUID_1;
    };

    $auth = Mockery::mock(TicketAuthorizationContract::class);
    foreach (['canCreateTicket', 'canUpdateTicket', 'canCloseTicket', 'canPostMessage', 'canRedactMessage'] as $m) {
        $auth->shouldReceive($m)->andReturn(true)->byDefault();
    }
    $auth->shouldReceive('canViewInternalMessages')->andReturn(true)->byDefault();

    $cap = Mockery::mock(UserCapabilityContract::class);
    $cap->shouldReceive('getUserLevel')->andReturn($this->level);
    $cap->shouldReceive('getUserAssignedTickets')->andReturn(collect([]));

    $this->tickets = new TicketService($auth);
    $this->messages = new TicketMessageService($auth);
    $this->assignments = new AssignmentService($auth, $cap);

    $this->makeTicket = fn (TicketStatus $status = TicketStatus::IN_PROGRESS) => Ticket::create([
        'subject' => 'Contract',
        'description' => 'x',
        'status' => $status,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);
});

describe('domain event matrix — one event per transition', function () {
    it('emits TicketCreated on create', function () {
        $this->tickets->createTicket([
            'subject' => 'New',
            'description' => 'x',
            'department_id' => $this->department->id,
        ], $this->actor);

        Event::assertDispatched(TicketCreated::class, 1);
    });

    it('emits TicketAssigned on assign', function () {
        $ticket = ($this->makeTicket)(TicketStatus::NEW);
        $agent = new class
        {
            public string $id = TestCase::USER_UUID_2;
        };

        $this->assignments->assignAgent($ticket, $agent, $this->actor);

        Event::assertDispatched(TicketAssigned::class, 1);
    });

    it('emits TicketStatusChanged on a non-terminal transition', function () {
        $ticket = ($this->makeTicket)(TicketStatus::NEW);

        $this->tickets->updateTicketStatus($ticket, TicketStatus::IN_PROGRESS, $this->actor);

        Event::assertDispatched(TicketStatusChanged::class, 1);
    });

    it('emits TicketResolved (and not TicketStatusChanged) on resolve', function () {
        $ticket = ($this->makeTicket)();

        $this->tickets->resolveTicket($ticket, $this->actor);

        Event::assertDispatched(TicketResolved::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
    });

    it('emits TicketClosed (and not TicketStatusChanged) on close', function () {
        $ticket = ($this->makeTicket)();

        $this->tickets->closeTicket($ticket, $this->actor);

        Event::assertDispatched(TicketClosed::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
    });

    it('emits TicketCancelled (and not TicketStatusChanged) on cancel', function () {
        $ticket = ($this->makeTicket)();

        $this->tickets->cancelTicket($ticket, $this->actor);

        Event::assertDispatched(TicketCancelled::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
    });

    it('emits TicketMessagePosted on post', function () {
        $ticket = ($this->makeTicket)();

        $this->messages->post($ticket, $this->actor, 'hello', MessageAuthorRole::CLIENT);

        Event::assertDispatched(TicketMessagePosted::class, 1);
    });

    it('emits TicketMessageRedacted on redact', function () {
        $ticket = ($this->makeTicket)();
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => TestCase::USER_UUID_1,
            'author_role' => MessageAuthorRole::CLIENT,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => 'sensitive',
        ]);

        $this->messages->redact($message, $this->actor, 'pii');

        Event::assertDispatched(TicketMessageRedacted::class, 1);
    });
});

describe('D1 — updateTicketStatus delegates to terminal states', function () {
    it('to RESOLVED emits TicketResolved, not TicketStatusChanged', function () {
        $ticket = ($this->makeTicket)();

        $this->tickets->updateTicketStatus($ticket, TicketStatus::RESOLVED, $this->actor);

        Event::assertDispatched(TicketResolved::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
    });

    it('to CLOSED emits TicketClosed, not TicketStatusChanged, and sets closed_at/resolved_by', function () {
        $ticket = ($this->makeTicket)();

        $result = $this->tickets->updateTicketStatus($ticket, TicketStatus::CLOSED, $this->actor);

        Event::assertDispatched(TicketClosed::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
        expect($result->closed_at)->not->toBeNull()
            ->and($result->resolved_by)->toBe(TestCase::USER_UUID_1);
    });

    it('to CANCELLED emits TicketCancelled, not TicketStatusChanged', function () {
        $ticket = ($this->makeTicket)();

        $this->tickets->updateTicketStatus($ticket, TicketStatus::CANCELLED, $this->actor);

        Event::assertDispatched(TicketCancelled::class, 1);
        Event::assertNotDispatched(TicketStatusChanged::class);
    });
});
