<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @experimental Opcional-experimental (ADR-004): outside the v1.0 semver
 *               promise. Emitted only when evaluation is enabled (off by default).
 */
class TicketEvaluated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TicketEvaluation $evaluation
    ) {}
}
