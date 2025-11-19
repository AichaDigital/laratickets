<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Events;

use AichaDigital\Laratickets\Models\EscalationRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscalationRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public EscalationRequest $escalationRequest
    ) {}
}
