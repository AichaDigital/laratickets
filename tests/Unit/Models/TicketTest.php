<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;

beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create([
        'name' => 'Technical',
        'active' => true,
    ]);
});

it('can create a ticket with enums', function () {
    $ticket = Ticket::create([
        'subject' => 'Test ticket',
        'description' => 'Test description',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::HIGH,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class)
        ->and($ticket->status)->toBe(TicketStatus::NEW)
        ->and($ticket->user_priority)->toBe(Priority::HIGH)
        ->and($ticket->isOpen())->toBeTrue()
        ->and($ticket->isClosed())->toBeFalse();
});

it('can check if ticket is open', function () {
    $ticket = Ticket::create([
        'subject' => 'Test',
        'description' => 'Test',
        'status' => TicketStatus::IN_PROGRESS,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    expect($ticket->isOpen())->toBeTrue();
});

it('can check if ticket is closed', function () {
    $ticket = Ticket::create([
        'subject' => 'Test',
        'description' => 'Test',
        'status' => TicketStatus::CLOSED,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    expect($ticket->isClosed())->toBeTrue();
});

it('can check if ticket can escalate', function () {
    $ticket = Ticket::create([
        'subject' => 'Test',
        'description' => 'Test',
        'status' => TicketStatus::NEW,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    expect($ticket->canEscalate())->toBeTrue();
});

it('cannot escalate if has active escalation', function () {
    $ticket = Ticket::create([
        'subject' => 'Test',
        'description' => 'Test',
        'status' => TicketStatus::ESCALATION_REQUESTED,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    expect($ticket->canEscalate())->toBeFalse();
});

it('can scope open tickets', function () {
    Ticket::create([
        'subject' => 'Open',
        'description' => 'Test',
        'status' => TicketStatus::NEW,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    Ticket::create([
        'subject' => 'Closed',
        'description' => 'Test',
        'status' => TicketStatus::CLOSED,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    $openTickets = Ticket::open()->get();

    expect($openTickets)->toHaveCount(1)
        ->and($openTickets->first()->subject)->toBe('Open');
});
