<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\EscalationApproved;
use AichaDigital\Laratickets\Events\EscalationRejected;
use AichaDigital\Laratickets\Events\EscalationRequested;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Facades\DB;

class EscalationService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    public function requestEscalation(
        Ticket $ticket,
        TicketLevel $targetLevel,
        string $justification,
        $requester,
        bool $isAutomatic = false
    ): EscalationRequest {
        if (! $isAutomatic && ! $this->authorization->canRequestEscalation($requester, $ticket)) {
            throw new \RuntimeException('User is not authorized to request escalation');
        }

        if (! $ticket->canEscalate()) {
            throw new \RuntimeException('Ticket cannot be escalated');
        }

        if ($targetLevel->level <= $ticket->currentLevel->level) {
            throw new \RuntimeException('Target level must be higher than current level');
        }

        return DB::transaction(function () use ($ticket, $targetLevel, $justification, $requester, $isAutomatic) {
            $escalationRequest = EscalationRequest::create([
                'ticket_id' => $ticket->id,
                'from_level_id' => $ticket->current_level_id,
                'to_level_id' => $targetLevel->id,
                'requester_id' => $requester->{config('laratickets.user.id_column', 'id')},
                'justification' => $justification,
                'status' => 'pending',
                'is_automatic' => $isAutomatic,
            ]);

            $ticket->update([
                'status' => TicketStatus::ESCALATION_REQUESTED,
                'requested_level_id' => $targetLevel->id,
            ]);

            event(new EscalationRequested($escalationRequest->load(['ticket', 'fromLevel', 'toLevel'])));

            return $escalationRequest->fresh();
        });
    }

    public function approveEscalation(EscalationRequest $request, $approver): EscalationRequest
    {
        if (! $this->authorization->canApproveEscalation($approver, $request)) {
            throw new \RuntimeException('User is not authorized to approve this escalation');
        }

        if (! $request->isPending()) {
            throw new \RuntimeException('Escalation request is not pending');
        }

        return DB::transaction(function () use ($request, $approver) {
            $request->approve($approver->{config('laratickets.user.id_column', 'id')});

            $ticket = $request->ticket;
            $newDeadline = now()->addHours($request->toLevel->default_sla_hours);

            $ticket->update([
                'current_level_id' => $request->to_level_id,
                'requested_level_id' => null,
                'status' => TicketStatus::ESCALATED,
                'estimated_deadline' => $newDeadline,
            ]);

            event(new EscalationApproved($request->load(['ticket', 'fromLevel', 'toLevel'])));

            return $request->fresh();
        });
    }

    public function rejectEscalation(EscalationRequest $request, $approver, string $reason): EscalationRequest
    {
        if (! $this->authorization->canApproveEscalation($approver, $request)) {
            throw new \RuntimeException('User is not authorized to reject this escalation');
        }

        if (! $request->isPending()) {
            throw new \RuntimeException('Escalation request is not pending');
        }

        return DB::transaction(function () use ($request, $approver, $reason) {
            $request->reject($approver->{config('laratickets.user.id_column', 'id')}, $reason);

            $ticket = $request->ticket;
            $ticket->update([
                'status' => TicketStatus::IN_PROGRESS,
                'requested_level_id' => null,
            ]);

            event(new EscalationRejected($request->load(['ticket', 'fromLevel', 'toLevel'])));

            return $request->fresh();
        });
    }

    public function autoEscalateByTimeout(Ticket $ticket): ?EscalationRequest
    {
        if (! config('laratickets.levels.auto_escalation_enabled', true)) {
            return null;
        }

        if (! $ticket->isOverdue() || ! $ticket->currentLevel->can_escalate) {
            return null;
        }

        $nextLevel = TicketLevel::where('level', $ticket->currentLevel->level + 1)->first();

        if (! $nextLevel) {
            return null;
        }

        // Create system user for automatic escalation
        $systemUser = (object) [config('laratickets.user.id_column', 'id') => null];

        return $this->requestEscalation(
            $ticket,
            $nextLevel,
            'Automatic escalation due to SLA breach',
            $systemUser,
            true
        );
    }

    public function autoApproveSystemEscalation(EscalationRequest $request): EscalationRequest
    {
        if (! $request->is_automatic || ! $request->isPending()) {
            throw new \RuntimeException('Only pending automatic escalations can be auto-approved');
        }

        return DB::transaction(function () use ($request) {
            $request->update([
                'status' => 'approved',
                'resolved_at' => now(),
            ]);

            $ticket = $request->ticket;
            $newDeadline = now()->addHours($request->toLevel->default_sla_hours);

            $ticket->update([
                'current_level_id' => $request->to_level_id,
                'requested_level_id' => null,
                'status' => TicketStatus::ESCALATED,
                'estimated_deadline' => $newDeadline,
            ]);

            event(new EscalationApproved($request->load(['ticket', 'fromLevel', 'toLevel'])));

            return $request->fresh();
        });
    }
}
