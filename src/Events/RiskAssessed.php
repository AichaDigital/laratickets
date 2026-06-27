<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\RiskAssessment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @experimental Opcional-experimental (ADR-004): outside the v1.0 semver
 *               promise. Emitted only when risk assessment is enabled (off by default).
 */
class RiskAssessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RiskAssessment $riskAssessment
    ) {}
}
