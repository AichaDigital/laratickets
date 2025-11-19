<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketEvaluated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TicketEvaluation $evaluation
    ) {}
}
