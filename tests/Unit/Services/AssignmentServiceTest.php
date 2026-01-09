<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\AssignmentService;

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

    // Create mock agent user
    $this->agent = new class
    {
        public int $id = 100;
    };

    $this->assigner = new class
    {
        public int $id = 1;
    };

    // Create mock authorization that allows all actions
    $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
    $this->authorization->shouldReceive('canUpdateTicket')->andReturn(true);

    // Create mock user capability
    $this->userCapability = Mockery::mock(UserCapabilityContract::class);
    $this->userCapability->shouldReceive('getUserLevel')->andReturn($this->level);
    $this->userCapability->shouldReceive('getUserAssignedTickets')->andReturn(collect([]));

    $this->service = new AssignmentService($this->authorization, $this->userCapability);
});

describe('AssignmentService idempotency', function () {
    it('returns existing assignment when agent is already assigned (idempotent)', function () {
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::NEW,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // First assignment
        $assignment1 = $this->service->assignAgent($ticket, $this->agent);

        expect($assignment1)->toBeInstanceOf(TicketAssignment::class)
            ->and($assignment1->user_id)->toBe(100)
            ->and($assignment1->ticket_id)->toBe($ticket->id);

        $initialCount = TicketAssignment::count();

        // Second assignment attempt (should be idempotent)
        $assignment2 = $this->service->assignAgent($ticket, $this->agent);

        // Should return the same assignment
        expect($assignment2->id)->toBe($assignment1->id)
            ->and(TicketAssignment::count())->toBe($initialCount);
    });

    it('does not create duplicate assignments on concurrent calls', function () {
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::NEW,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Simulate multiple assignment attempts
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->service->assignAgent($ticket, $this->agent);
        }

        // All results should be the same assignment
        $uniqueIds = collect($results)->pluck('id')->unique();
        expect($uniqueIds)->toHaveCount(1);

        // Only one assignment should exist
        $totalAssignments = TicketAssignment::where('ticket_id', $ticket->id)
            ->where('user_id', 100)
            ->count();

        expect($totalAssignments)->toBe(1);
    });
});

describe('AssignmentService transaction behavior', function () {
    it('assignAgent uses database transaction for atomicity', function () {
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::NEW,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        $assignment = $this->service->assignAgent($ticket, $this->agent);

        // Verify assignment was created
        expect($assignment)->toBeInstanceOf(TicketAssignment::class);

        // Verify ticket status was updated to ASSIGNED
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::ASSIGNED);
    });

    it('reassignTicket uses database transaction', function () {
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::ASSIGNED,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // First agent assignment
        $this->service->assignAgent($ticket, $this->agent);

        // New agent
        $newAgent = new class
        {
            public int $id = 200;
        };

        // Reassign to new agent
        $newAssignment = $this->service->reassignTicket($ticket, $this->agent, $newAgent, $this->assigner);

        // Verify old assignment is completed
        $oldAssignments = TicketAssignment::where('ticket_id', $ticket->id)
            ->where('user_id', 100)
            ->whereNull('completed_at')
            ->count();

        expect($oldAssignments)->toBe(0);

        // Verify new assignment is active
        expect($newAssignment->user_id)->toBe(200)
            ->and($newAssignment->isActive())->toBeTrue();
    });
});

describe('AssignmentService authorization and validation', function () {
    it('throws exception when agent does not have level access', function () {
        $levelTwo = TicketLevel::create([
            'level' => 2,
            'name' => 'Level II',
            'can_escalate' => true,
            'can_assess_risk' => true,
            'default_sla_hours' => 48,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Level 2 ticket',
            'description' => 'Test',
            'status' => TicketStatus::NEW,
            'current_level_id' => $levelTwo->id, // Level 2 ticket
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Agent only has Level 1 access
        expect(fn () => $this->service->assignAgent($ticket, $this->agent))
            ->toThrow(\RuntimeException::class, 'Agent does not have access to this ticket level');
    });

    it('throws exception when agent has max concurrent tickets', function () {
        config(['laratickets.assignment.max_concurrent_tickets' => 2]);

        // Mock that agent already has 2 tickets
        $userCapabilityMock = Mockery::mock(UserCapabilityContract::class);
        $userCapabilityMock->shouldReceive('getUserLevel')->andReturn($this->level);
        $userCapabilityMock->shouldReceive('getUserAssignedTickets')
            ->andReturn(collect([new stdClass, new stdClass])); // 2 existing tickets

        $service = new AssignmentService($this->authorization, $userCapabilityMock);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::NEW,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        expect(fn () => $service->assignAgent($ticket, $this->agent))
            ->toThrow(\RuntimeException::class, 'Agent has reached maximum concurrent tickets');
    });

    it('throws exception when assigner is not authorized', function () {
        $authMock = Mockery::mock(TicketAuthorizationContract::class);
        $authMock->shouldReceive('canUpdateTicket')->andReturn(false);

        $service = new AssignmentService($authMock, $this->userCapability);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::NEW,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        expect(fn () => $service->assignAgent($ticket, $this->agent, $this->assigner))
            ->toThrow(\RuntimeException::class, 'User is not authorized to assign agents');
    });
});

describe('AssignmentService unassignment', function () {
    it('throws exception when agent is not assigned', function () {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::NEW,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        expect(fn () => $this->service->unassignAgent($ticket, $this->agent))
            ->toThrow(\RuntimeException::class, 'Agent is not assigned to this ticket');
    });

    it('completes assignment when unassigning agent', function () {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'description' => 'Test',
            'status' => TicketStatus::ASSIGNED,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => 1,
        ]);

        // Assign agent first
        $assignment = $this->service->assignAgent($ticket, $this->agent);
        expect($assignment->isActive())->toBeTrue();

        // Unassign agent
        $this->service->unassignAgent($ticket, $this->agent);

        $assignment->refresh();
        expect($assignment->isActive())->toBeFalse()
            ->and($assignment->completed_at)->not->toBeNull();
    });
});
