<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\RiskAssessment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiskAssessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RiskAssessment $riskAssessment
    ) {}
}
