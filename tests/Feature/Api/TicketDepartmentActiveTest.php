<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Auth\Authenticatable;

/*
|--------------------------------------------------------------------------
| AID-1085 — the package's own REST surface
|--------------------------------------------------------------------------
|
| `TicketController::store()` goes through TicketService, but `update()` writes
| straight to the model — so for the update path the FormRequest is the only
| guard the package has. These exercise the real routes end to end rather than
| trusting that the controller still type-hints the requests we hardened.
|
| Sanctum is not part of the package's test harness, so the auth middleware is
| skipped; FormRequest validation is resolved by the router, not by middleware,
| so it still runs — which is precisely what is under test here.
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

    $this->actor = new class implements Authenticatable
    {
        public string $id = TestCase::USER_UUID_1;

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    // Only the auth middleware is skipped. SubstituteBindings must keep
    // running: the update rule reads the ticket's current department from the
    // route, and dropping every middleware would quietly change what is tested.
    $this->withoutMiddleware(Authenticate::class);
    $this->actingAs($this->actor);
});

function apiPayload(int $departmentId): array
{
    return [
        'subject' => 'Cannot reach the control panel',
        'description' => 'The panel times out on every login attempt.',
        'priority' => Priority::MEDIUM->value,
        'department_id' => $departmentId,
    ];
}

function existingTicketIn(Department $department): Ticket
{
    return Ticket::create([
        'subject' => 'Historical ticket',
        'description' => 'Opened while the department was still in service.',
        'user_priority' => Priority::MEDIUM->value,
        'department_id' => $department->id,
        'current_level_id' => test()->level->id,
        'created_by' => TestCase::USER_UUID_1,
        'status' => TicketStatus::NEW,
    ]);
}

it('rejects POST /tickets into a retired department', function () {
    $response = $this->postJson('/api/v1/laratickets/tickets', apiPayload($this->retiredDepartment->id));

    $response->assertStatus(422)->assertJsonValidationErrors('department_id');
    expect(Ticket::count())->toBe(0);
});

it('accepts POST /tickets into an active department', function () {
    // Negative control: a rule that rejected everything would pass the test
    // above and fail here.
    $response = $this->postJson('/api/v1/laratickets/tickets', apiPayload($this->activeDepartment->id));

    $response->assertStatus(201);
    expect(Ticket::count())->toBe(1);
});

it('rejects PATCH /tickets/{ticket} that moves a ticket into a retired department', function () {
    $ticket = existingTicketIn($this->activeDepartment);

    $response = $this->patchJson(
        '/api/v1/laratickets/tickets/'.$ticket->id,
        ['department_id' => $this->retiredDepartment->id],
    );

    $response->assertStatus(422)->assertJsonValidationErrors('department_id');
    expect($ticket->fresh()->department_id)->toBe($this->activeDepartment->id);
});

it('still lets a ticket already in a retired department be edited', function () {
    // The permanence case, over the real route: a client resubmitting the whole
    // form sends back the department it is already in, and an unrelated edit
    // must not be collateral damage.
    $ticket = existingTicketIn($this->retiredDepartment);

    $response = $this->patchJson('/api/v1/laratickets/tickets/'.$ticket->id, [
        'subject' => 'A corrected subject',
        'department_id' => $this->retiredDepartment->id,
    ]);

    $response->assertStatus(200);
    expect($ticket->fresh()->subject)->toBe('A corrected subject');
});

it('accepts a retired department over the API when enforcement is switched off', function () {
    config()->set('laratickets.departments.enforce_active', false);

    $response = $this->postJson('/api/v1/laratickets/tickets', apiPayload($this->retiredDepartment->id));

    $response->assertStatus(201);
    expect(Ticket::first()->department_id)->toBe($this->retiredDepartment->id);
});
