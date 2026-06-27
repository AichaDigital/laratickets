<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\RiskLevel;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\EscalationService;
use AichaDigital\Laratickets\Services\RiskAssessmentService;

/**
 * Minimal typed-failure coverage for the @experimental RiskAssessmentService.
 * Configs are set explicitly so the tests do not depend on the v1.0 default
 * (risk_assessment.enabled is OFF by default in v1.0).
 */
beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => true,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->ticket = Ticket::create([
        'subject' => 'Assess me',
        'description' => 'x',
        'status' => TicketStatus::IN_PROGRESS,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => 1,
    ]);

    $this->actor = new class
    {
        public int $id = 5;
    };

    $this->auth = Mockery::mock(TicketAuthorizationContract::class);
    $this->cap = Mockery::mock(UserCapabilityContract::class);
    $this->escalation = Mockery::mock(EscalationService::class);

    $this->service = new RiskAssessmentService($this->auth, $this->cap, $this->escalation);
});

describe('RiskAssessmentService typed failures', function () {
    it('throws TicketStateException when risk assessment is disabled', function () {
        config()->set('laratickets.risk_assessment.enabled', false);

        expect(fn () => $this->service->assessRisk($this->ticket, $this->actor, RiskLevel::HIGH, 'x'))
            ->toThrow(TicketStateException::class);
    });

    it('throws TicketAuthorizationException when the assessor is not authorized', function () {
        config()->set('laratickets.risk_assessment.enabled', true);
        $this->auth->shouldReceive('canAssessRisk')->andReturn(false);

        expect(fn () => $this->service->assessRisk($this->ticket, $this->actor, RiskLevel::HIGH, 'x'))
            ->toThrow(TicketAuthorizationException::class);
    });
});
