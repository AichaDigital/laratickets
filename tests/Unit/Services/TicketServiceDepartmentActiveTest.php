<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Exceptions\InactiveDepartmentException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\TicketService;
use AichaDigital\Laratickets\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| AID-1085 — createTicket() must not file into a retired department
|--------------------------------------------------------------------------
|
| `Department::scopeActive()` shipped with the model and had zero callers in
| src/. The service wrote `$data['department_id']` unchecked, so any caller —
| including the package's own REST API — could open a ticket in a department
| the operator had taken out of service.
|
| The guard is reversible through `laratickets.departments.enforce_active`,
| read at the point of use so a consumer with a PUBLISHED config (whose own
| `departments` block replaces the package's, `mergeConfigFrom` being a shallow
| array_merge) still gets the safe default.
|
*/

beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->activeDepartment = Department::create(['name' => 'Technical', 'active' => true]);
    $this->retiredDepartment = Department::create(['name' => 'Legacy hosting', 'active' => false]);

    $this->user = new class
    {
        public string $id = TestCase::USER_UUID_1;
    };

    $authorization = Mockery::mock(TicketAuthorizationContract::class);
    $authorization->shouldReceive('canCreateTicket')->andReturn(true);

    $this->service = new TicketService($authorization);
});

$payload = fn (Department $department): array => [
    'subject' => 'Cannot reach the control panel',
    'description' => 'The panel times out on every login attempt.',
    'priority' => 'medium',
    'department_id' => $department->id,
];

it('refuses to open a ticket in a retired department', function () use ($payload) {
    expect(fn () => $this->service->createTicket($payload($this->retiredDepartment), $this->user))
        ->toThrow(InactiveDepartmentException::class);

    expect(Ticket::count())->toBe(0);
});

it('still opens a ticket in an active department', function () use ($payload) {
    // Negative control: a guard that rejected everything would pass the test
    // above and fail here.
    $ticket = $this->service->createTicket($payload($this->activeDepartment), $this->user);

    expect($ticket->department_id)->toBe($this->activeDepartment->id);
    expect(Ticket::count())->toBe(1);
});

it('opens a ticket in a retired department when enforcement is switched off', function () use ($payload) {
    // The documented escape hatch: the pre-1.2.0 behaviour, recoverable by an
    // unknown consumer whose flow legitimately routes into a hidden department.
    config()->set('laratickets.departments.enforce_active', false);

    $ticket = $this->service->createTicket($payload($this->retiredDepartment), $this->user);

    expect($ticket->department_id)->toBe($this->retiredDepartment->id);
});
