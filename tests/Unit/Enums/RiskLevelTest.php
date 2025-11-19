<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\RiskLevel;

it('has correct risk level values', function () {
    expect(RiskLevel::LOW->value)->toBe('low')
        ->and(RiskLevel::MEDIUM->value)->toBe('medium')
        ->and(RiskLevel::HIGH->value)->toBe('high')
        ->and(RiskLevel::CRITICAL->value)->toBe('critical');
});

it('returns correct labels', function () {
    expect(RiskLevel::LOW->label())->toBe('Low Risk')
        ->and(RiskLevel::MEDIUM->label())->toBe('Medium Risk')
        ->and(RiskLevel::HIGH->label())->toBe('High Risk')
        ->and(RiskLevel::CRITICAL->label())->toBe('Critical Risk');
});

it('can determine if should auto-escalate', function () {
    expect(RiskLevel::CRITICAL->shouldAutoEscalate())->toBeTrue()
        ->and(RiskLevel::HIGH->shouldAutoEscalate())->toBeFalse()
        ->and(RiskLevel::MEDIUM->shouldAutoEscalate())->toBeFalse()
        ->and(RiskLevel::LOW->shouldAutoEscalate())->toBeFalse();
});

it('returns correct numeric values', function () {
    expect(RiskLevel::LOW->numericValue())->toBe(1)
        ->and(RiskLevel::MEDIUM->numericValue())->toBe(2)
        ->and(RiskLevel::HIGH->numericValue())->toBe(3)
        ->and(RiskLevel::CRITICAL->numericValue())->toBe(4);
});
