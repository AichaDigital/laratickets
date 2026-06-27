<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\EvaluationService;

/**
 * Minimal typed-failure coverage for the @experimental EvaluationService.
 * Configs are set explicitly so the tests do not depend on the v1.0 default
 * (evaluation.enabled is OFF by default in v1.0).
 */
beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->ticket = Ticket::create([
        'subject' => 'Rate me',
        'description' => 'x',
        'status' => TicketStatus::RESOLVED,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    $this->actor = new class
    {
        public int $id = 5;
    };
});

describe('EvaluationService typed failures', function () {
    it('throws TicketStateException when evaluation is disabled', function () {
        config()->set('laratickets.evaluation.enabled', false);
        $service = new EvaluationService(Mockery::mock(TicketAuthorizationContract::class));

        expect(fn () => $service->evaluateTicket($this->ticket, $this->actor, 5.0))
            ->toThrow(TicketStateException::class);
    });

    it('throws TicketAuthorizationException when the evaluator is not authorized', function () {
        config()->set('laratickets.evaluation.enabled', true);
        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canEvaluateTicket')->andReturn(false);
        $service = new EvaluationService($auth);

        expect(fn () => $service->evaluateTicket($this->ticket, $this->actor, 5.0))
            ->toThrow(TicketAuthorizationException::class);
    });
});
