<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Middleware;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Models\Ticket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTicketAuthorization
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $ticket = $request->route('ticket');

        if (! $ticket instanceof Ticket) {
            return $next($request);
        }

        $user = $request->user();

        $authorized = match ($ability) {
            'view' => $this->authorization->canViewTicket($user, $ticket),
            'update' => $this->authorization->canUpdateTicket($user, $ticket),
            'delete' => $this->authorization->canDeleteTicket($user, $ticket),
            'close' => $this->authorization->canCloseTicket($user, $ticket),
            'escalate' => $this->authorization->canRequestEscalation($user, $ticket),
            'evaluate' => $this->authorization->canEvaluateTicket($user, $ticket),
            'assess-risk' => $this->authorization->canAssessRisk($user, $ticket),
            default => false,
        };

        if (! $authorized) {
            abort(403, 'Unauthorized action on ticket');
        }

        return $next($request);
    }
}
