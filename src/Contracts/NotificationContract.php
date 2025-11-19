<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Contracts;

use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketEvaluation;

interface NotificationContract
{
    /**
     * Notify when a ticket is created
     */
    public function notifyTicketCreated(Ticket $ticket): void;

    /**
     * Notify when a ticket is assigned to a user
     */
    public function notifyTicketAssigned(Ticket $ticket, $user): void;

    /**
     * Notify when an escalation is requested
     */
    public function notifyEscalationRequested(EscalationRequest $request): void;

    /**
     * Notify when an escalation is approved
     */
    public function notifyEscalationApproved(EscalationRequest $request): void;

    /**
     * Notify when an escalation is rejected
     */
    public function notifyEscalationRejected(EscalationRequest $request): void;

    /**
     * Notify when a ticket is closed
     */
    public function notifyTicketClosed(Ticket $ticket): void;

    /**
     * Notify when an evaluation is received
     */
    public function notifyEvaluationReceived($user, TicketEvaluation $evaluation): void;

    /**
     * Notify when SLA is breached
     */
    public function notifySLABreached(Ticket $ticket): void;
}
