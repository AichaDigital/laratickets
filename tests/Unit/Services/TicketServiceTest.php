<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\TicketService;

beforeEach(function () {
    // Create required level and department
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

    // Create a mock user object
    $this->user = new class
    {
        public int $id = 1;
    };

    // Create mock authorization that allows all actions
    $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
    $this->authorization->shouldReceive('canCreateTicket')->andReturn(true);
    $this->authorization->shouldReceive('canUpdateTicket')->andReturn(true);
    $this->authorization->shouldReceive('canCloseTicket')->andReturn(true);

    $this->service = new TicketService($this->authorization);
});

describe('TicketService transaction behavior', function () {
    it('cancelTicket uses database transaction for atomicity', function () {
        // Create a ticket with active assignments
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::IN_PROGRESS,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Create an active assignment
        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'user_id' => 1,
            'assigned_at' => now(),
        ]);

        // Cancel the ticket
        $result = $this->service->cancelTicket($ticket, $this->user);

        // Verify ticket is cancelled
        expect($result->status)->toBe(TicketStatus::CANCELLED)
            ->and($result->closed_at)->not->toBeNull();

        // Verify all assignments are completed
        $activeAssignments = TicketAssignment::where('ticket_id', $ticket->id)
            ->whereNull('completed_at')
            ->count();

        expect($activeAssignments)->toBe(0);
    });

    it('closeTicket uses database transaction for atomicity', function () {
        // Create a ticket with active assignments
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::RESOLVED,
            'user_priority' => Priority::HIGH,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Create multiple active assignments
        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'user_id' => 1,
            'assigned_at' => now(),
        ]);

        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'user_id' => 2,
            'assigned_at' => now(),
        ]);

        // Close the ticket
        $result = $this->service->closeTicket($ticket, $this->user);

        // Verify ticket is closed
        expect($result->status)->toBe(TicketStatus::CLOSED)
            ->and($result->closed_at)->not->toBeNull()
            ->and((int) $result->resolved_by)->toBe(1);

        // Verify all assignments are completed
        $activeAssignments = TicketAssignment::where('ticket_id', $ticket->id)
            ->whereNull('completed_at')
            ->count();

        expect($activeAssignments)->toBe(0);
    });

    it('cancelTicket completes all active assignments atomically', function () {
        $ticket = Ticket::create([
            'subject' => 'Multi-agent ticket',
            'description' => 'Test',
            'status' => TicketStatus::IN_PROGRESS,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Create 3 active assignments
        for ($i = 1; $i <= 3; $i++) {
            TicketAssignment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $i,
                'assigned_at' => now(),
            ]);
        }

        expect(TicketAssignment::where('ticket_id', $ticket->id)->active()->count())->toBe(3);

        $this->service->cancelTicket($ticket, $this->user);

        // All should be completed in single transaction
        expect(TicketAssignment::where('ticket_id', $ticket->id)->active()->count())->toBe(0)
            ->and(TicketAssignment::where('ticket_id', $ticket->id)->completed()->count())->toBe(3);
    });
});

describe('TicketService authorization checks', function () {
    it('throws exception when user cannot cancel ticket', function () {
        $authMock = Mockery::mock(TicketAuthorizationContract::class);
        $authMock->shouldReceive('canUpdateTicket')->andReturn(false);

        $service = new TicketService($authMock);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::IN_PROGRESS,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        expect(fn () => $service->cancelTicket($ticket, $this->user))
            ->toThrow(\RuntimeException::class, 'User is not authorized to cancel this ticket');
    });

    it('throws exception when user cannot close ticket', function () {
        $authMock = Mockery::mock(TicketAuthorizationContract::class);
        $authMock->shouldReceive('canCloseTicket')->andReturn(false);

        $service = new TicketService($authMock);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::RESOLVED,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        expect(fn () => $service->closeTicket($ticket, $this->user))
            ->toThrow(\RuntimeException::class, 'User is not authorized to close this ticket');
    });
});

describe('TicketService status transitions', function () {
    it('updateTicketStatus does not update if status is same', function () {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::IN_PROGRESS,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        $result = $this->service->updateTicketStatus($ticket, TicketStatus::IN_PROGRESS, $this->user);

        expect($result->status)->toBe(TicketStatus::IN_PROGRESS);
    });

    it('updateTicketStatus changes status correctly', function () {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::NEW,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        $result = $this->service->updateTicketStatus($ticket, TicketStatus::IN_PROGRESS, $this->user);

        expect($result->status)->toBe(TicketStatus::IN_PROGRESS);
    });
});
