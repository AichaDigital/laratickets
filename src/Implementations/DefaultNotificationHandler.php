<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Implementations;

use AichaDigital\Laratickets\Contracts\NotificationContract;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Support\Facades\Log;

class DefaultNotificationHandler implements NotificationContract
{
    public function notifyTicketCreated(Ticket $ticket): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Ticket created', ['ticket_id' => $ticket->id]);
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function notifyTicketAssigned(Ticket $ticket, $user): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Ticket assigned', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->{config('laratickets.user.id_column', 'id')},
        ]);
    }

    public function notifyEscalationRequested(EscalationRequest $request): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Escalation requested', ['escalation_id' => $request->id]);
    }

    public function notifyEscalationApproved(EscalationRequest $request): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Escalation approved', ['escalation_id' => $request->id]);
    }

    public function notifyEscalationRejected(EscalationRequest $request): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Escalation rejected', ['escalation_id' => $request->id]);
    }

    public function notifyTicketClosed(Ticket $ticket): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Ticket closed', ['ticket_id' => $ticket->id]);
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function notifyEvaluationReceived($user, TicketEvaluation $evaluation): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::info('Evaluation received', [
            'evaluation_id' => $evaluation->id,
            'user_id' => $user->{config('laratickets.user.id_column', 'id')},
        ]);
    }

    public function notifySLABreached(Ticket $ticket): void
    {
        if (! config('laratickets.notifications.enabled', true)) {
            return;
        }

        Log::warning('SLA breached', ['ticket_id' => $ticket->id]);
    }
}
