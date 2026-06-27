<?php

declare(strict_types=1);

/**
 * v1.0 (ADR-004 / D3) default config posture:
 * - experimental features (evaluation, agent rating, risk assessment) ship OFF
 * - the opcional-stable level auto-escalation stays ON (so castris keeps it)
 */
describe('v1.0 default config posture', function () {
    it('ships the experimental features OFF by default', function () {
        expect(config('laratickets.evaluation.enabled'))->toBeFalse()
            ->and(config('laratickets.evaluation.agent_rating_enabled'))->toBeFalse()
            ->and(config('laratickets.risk_assessment.enabled'))->toBeFalse();
    });

    it('keeps level auto-escalation ON by default', function () {
        expect(config('laratickets.levels.auto_escalation_enabled'))->toBeTrue();
    });
});
