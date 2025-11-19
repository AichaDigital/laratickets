<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Models\TicketLevel;

it('can create a ticket level', function () {
    $level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'description' => 'Basic support',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
        'active' => true,
    ]);

    expect($level)->toBeInstanceOf(TicketLevel::class)
        ->and($level->level)->toBe(1)
        ->and($level->name)->toBe('Level I')
        ->and($level->can_escalate)->toBeTrue()
        ->and($level->can_assess_risk)->toBeFalse();
});

it('can check if level is higher than another', function () {
    $level1 = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $level2 = TicketLevel::create([
        'level' => 2,
        'name' => 'Level II',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 48,
    ]);

    expect($level2->isHigherThan($level1))->toBeTrue()
        ->and($level1->isHigherThan($level2))->toBeFalse();
});

it('can check if can escalate to target level', function () {
    $level1 = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $level2 = TicketLevel::create([
        'level' => 2,
        'name' => 'Level II',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 48,
    ]);

    expect($level1->canEscalateTo($level2))->toBeTrue()
        ->and($level2->canEscalateTo($level1))->toBeFalse();
});

it('cannot escalate if escalation is disabled', function () {
    $level1 = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => false,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $level2 = TicketLevel::create([
        'level' => 2,
        'name' => 'Level II',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 48,
    ]);

    expect($level1->canEscalateTo($level2))->toBeFalse();
});
