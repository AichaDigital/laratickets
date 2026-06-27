<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\EscalationApproved;
use AichaDigital\Laratickets\Events\EscalationRejected;
use AichaDigital\Laratickets\Events\EscalationRequested;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\EscalationService;

beforeEach(function () {
    Event::fake([EscalationRequested::class, EscalationApproved::class, EscalationRejected::class]);

    $this->level1 = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->level2 = TicketLevel::create([
        'level' => 2,
        'name' => 'Level II',
        'can_escalate' => true,
        'can_assess_risk' => true,
        'default_sla_hours' => 48,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->requester = new class
    {
        public int $id = 10;
    };

    $this->approver = new class
    {
        public int $id = 20;
    };

    $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
    $this->authorization->shouldReceive('canRequestEscalation')->andReturn(true)->byDefault();
    $this->authorization->shouldReceive('canApproveEscalation')->andReturn(true)->byDefault();

    $this->service = new EscalationService($this->authorization);

    $this->makeTicket = fn (array $overrides = []) => Ticket::create(array_merge([
        'subject' => 'Escalate me',
        'description' => 'x',
        'status' => TicketStatus::IN_PROGRESS,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level1->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ], $overrides));
});

describe('EscalationService::requestEscalation', function () {
    it('requests escalation and emits EscalationRequested', function () {
        $ticket = ($this->makeTicket)();

        $request = $this->service->requestEscalation($ticket, $this->level2, 'Needs L2', $this->requester);

        expect($request->status)->toBe('pending')
            ->and($request->to_level_id)->toBe($this->level2->id)
            ->and($ticket->fresh()->status)->toBe(TicketStatus::ESCALATION_REQUESTED);

        Event::assertDispatched(EscalationRequested::class);
    });

    it('throws TicketAuthorizationException when requester is not authorized', function () {
        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canRequestEscalation')->andReturn(false);
        $service = new EscalationService($auth);
        $ticket = ($this->makeTicket)();

        expect(fn () => $service->requestEscalation($ticket, $this->level2, 'x', $this->requester))
            ->toThrow(TicketAuthorizationException::class);
    });

    it('throws TicketStateException when the ticket cannot be escalated', function () {
        $ticket = ($this->makeTicket)(['status' => TicketStatus::CLOSED]);

        expect(fn () => $this->service->requestEscalation($ticket, $this->level2, 'x', $this->requester))
            ->toThrow(TicketStateException::class);
    });
});

describe('EscalationService approve/reject', function () {
    it('approves a pending escalation and emits EscalationApproved', function () {
        $ticket = ($this->makeTicket)();
        $request = $this->service->requestEscalation($ticket, $this->level2, 'Needs L2', $this->requester);

        $approved = $this->service->approveEscalation($request, $this->approver);

        expect($approved->status)->toBe('approved')
            ->and($ticket->fresh()->status)->toBe(TicketStatus::ESCALATED);

        Event::assertDispatched(EscalationApproved::class);
    });

    it('rejects a pending escalation and emits EscalationRejected', function () {
        $ticket = ($this->makeTicket)();
        $request = $this->service->requestEscalation($ticket, $this->level2, 'Needs L2', $this->requester);

        $rejected = $this->service->rejectEscalation($request, $this->approver, 'Not warranted');

        expect($rejected->status)->toBe('rejected')
            ->and($rejected->rejection_reason)->toBe('Not warranted')
            ->and($ticket->fresh()->status)->toBe(TicketStatus::IN_PROGRESS);

        Event::assertDispatched(EscalationRejected::class);
    });
});

describe('EscalationService::autoEscalateByTimeout', function () {
    it('auto-escalates an overdue ticket via the system actor (no human requester)', function () {
        $ticket = ($this->makeTicket)(['estimated_deadline' => now()->subHour()]);

        $request = $this->service->autoEscalateByTimeout($ticket);

        expect($request)->toBeInstanceOf(EscalationRequest::class)
            ->and($request->is_automatic)->toBeTrue()
            ->and($request->requester_id)->toBeNull()
            ->and($ticket->fresh()->status)->toBe(TicketStatus::ESCALATION_REQUESTED);
    });

    it('does not auto-escalate when auto_escalation is disabled', function () {
        config()->set('laratickets.levels.auto_escalation_enabled', false);
        $ticket = ($this->makeTicket)(['estimated_deadline' => now()->subHour()]);

        expect($this->service->autoEscalateByTimeout($ticket))->toBeNull();
    });
});
