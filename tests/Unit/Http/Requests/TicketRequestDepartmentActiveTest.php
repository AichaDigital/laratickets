<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Http\Requests\StoreTicketRequest;
use AichaDigital\Laratickets\Http\Requests\UpdateTicketRequest;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| AID-1085 — the published validation contract must not accept a retired
| department
|--------------------------------------------------------------------------
|
| These two requests ARE the package's create/update contract: the REST
| controller type-hints them, and a consumer that does not want to re-derive
| the validation reuses them. `exists:departments,id` accepted any department,
| active or not — which is why `clientes` had the very same hole.
|
| Update is deliberately narrower than create: it rejects a NEW assignment to a
| retired department, but leaves a historical ticket that already sits in one
| fully editable. A client resubmitting a whole form would otherwise be unable
| to fix a typo in the subject.
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
});

/**
 * Runs a FormRequest's real rules() against a payload, with the route
 * parameters the request would see in production.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, mixed>  $routeParameters
 */
function validateAgainst(string $requestClass, array $payload, array $routeParameters = []): Illuminate\Contracts\Validation\Validator
{
    /** @var FormRequest $request */
    $request = $requestClass::create('/', 'POST', $payload);

    $route = new Route(['POST'], '/', []);
    $route->bind($request);

    foreach ($routeParameters as $name => $value) {
        $route->setParameter($name, $value);
    }

    $request->setRouteResolver(fn (): Route => $route);
    $request->setContainer(app());

    return Validator::make($payload, $request->rules());
}

/** @param array<string, mixed> $overrides */
function storePayload(int $departmentId, array $overrides = []): array
{
    return array_merge([
        'subject' => 'Cannot reach the control panel',
        'description' => 'The panel times out on every login attempt.',
        'priority' => Priority::MEDIUM->value,
        'department_id' => $departmentId,
    ], $overrides);
}

function ticketInDepartment(Department $department): Ticket
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

describe('StoreTicketRequest', function () {
    it('rejects a retired department', function () {
        $validator = validateAgainst(StoreTicketRequest::class, storePayload($this->retiredDepartment->id));

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('department_id'))->toBeTrue();
    });

    it('accepts an active department', function () {
        // Negative control.
        $validator = validateAgainst(StoreTicketRequest::class, storePayload($this->activeDepartment->id));

        expect($validator->fails())->toBeFalse();
    });

    it('accepts a retired department when enforcement is switched off', function () {
        config()->set('laratickets.departments.enforce_active', false);

        $validator = validateAgainst(StoreTicketRequest::class, storePayload($this->retiredDepartment->id));

        expect($validator->fails())->toBeFalse();
    });
});

describe('UpdateTicketRequest', function () {
    it('rejects moving a ticket into a retired department', function () {
        $ticket = ticketInDepartment($this->activeDepartment);

        $validator = validateAgainst(
            UpdateTicketRequest::class,
            ['department_id' => $this->retiredDepartment->id],
            ['ticket' => $ticket],
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('department_id'))->toBeTrue();
    });

    it('lets a ticket already sitting in a retired department be edited', function () {
        // The permanence case. A client resubmitting the whole form sends the
        // department it is already in; hardening update indiscriminately would
        // block an unrelated edit — fixing the subject — on every historical
        // ticket whose department was later retired. Forbidding that permanence
        // too is a data question (inventory + migration), not a validation one.
        $ticket = ticketInDepartment($this->retiredDepartment);

        $validator = validateAgainst(
            UpdateTicketRequest::class,
            ['subject' => 'A corrected subject', 'department_id' => $this->retiredDepartment->id],
            ['ticket' => $ticket],
        );

        expect($validator->fails())->toBeFalse();
    });

    it('accepts moving a ticket into an active department', function () {
        // Negative control.
        $ticket = ticketInDepartment($this->retiredDepartment);

        $validator = validateAgainst(
            UpdateTicketRequest::class,
            ['department_id' => $this->activeDepartment->id],
            ['ticket' => $ticket],
        );

        expect($validator->fails())->toBeFalse();
    });

    it('accepts moving into a retired department when enforcement is switched off', function () {
        config()->set('laratickets.departments.enforce_active', false);
        $ticket = ticketInDepartment($this->activeDepartment);

        $validator = validateAgainst(
            UpdateTicketRequest::class,
            ['department_id' => $this->retiredDepartment->id],
            ['ticket' => $ticket],
        );

        expect($validator->fails())->toBeFalse();
    });
});
