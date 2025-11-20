<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Enums\RiskLevel;
use AichaDigital\Laratickets\Events\RiskAssessed;
use AichaDigital\Laratickets\Models\RiskAssessment;
use AichaDigital\Laratickets\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RiskAssessmentService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization,
        protected UserCapabilityContract $userCapability,
        protected EscalationService $escalationService
    ) {}

    /**
     * Assess risk for a ticket
     *
     * @param  mixed  $assessor  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function assessRisk(
        Ticket $ticket,
        $assessor,
        RiskLevel $riskLevel,
        string $justification
    ): RiskAssessment {
        if (! config('laratickets.risk_assessment.enabled', true)) {
            throw new \RuntimeException('Risk assessment system is disabled');
        }

        if (! $this->authorization->canAssessRisk($assessor, $ticket)) {
            throw new \RuntimeException('User is not authorized to assess risk for this ticket');
        }

        // Verify assessor has required level
        $assessorLevel = $this->userCapability->getUserLevel($assessor);
        $requiredLevels = config('laratickets.risk_assessment.required_levels', [3, 4]);

        if (! $assessorLevel || ! in_array($assessorLevel->level, $requiredLevels)) {
            throw new \RuntimeException('User does not have required level to assess risk');
        }

        return DB::transaction(function () use ($ticket, $assessor, $riskLevel, $justification) {
            $assessment = RiskAssessment::create([
                'ticket_id' => $ticket->id,
                'assessor_id' => $assessor->{config('laratickets.user.id_column', 'id')},
                'risk_level' => $riskLevel,
                'justification' => $justification,
            ]);

            // Update ticket's assessed risk
            $ticket->update(['assessed_risk' => $riskLevel]);

            event(new RiskAssessed($assessment->load('ticket')));

            // Auto-escalate if critical and enabled
            if ($assessment->shouldAutoEscalate() && config('laratickets.risk_assessment.auto_escalate_on_critical', true)) {
                $this->escalateByRisk($ticket, $assessor);
            }

            return $assessment;
        });
    }

    /**
     * Check if user can assess risk
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canUserAssessRisk($user): bool
    {
        if (! config('laratickets.risk_assessment.enabled', true)) {
            return false;
        }

        $userLevel = $this->userCapability->getUserLevel($user);
        $requiredLevels = config('laratickets.risk_assessment.required_levels', [3, 4]);

        return $userLevel && in_array($userLevel->level, $requiredLevels);
    }

    /**
     * Get high risk tickets
     *
     * @return Collection<int, \AichaDigital\Laratickets\Models\Ticket>
     */
    public function getHighRiskTickets(): Collection
    {
        return Ticket::highRisk()
            ->with(['currentLevel', 'department', 'latestRiskAssessment'])
            ->get();
    }

    /**
     * Get critical risk tickets
     *
     * @return Collection<int, \AichaDigital\Laratickets\Models\Ticket>
     */
    public function getCriticalRiskTickets(): Collection
    {
        return Ticket::where('assessed_risk', RiskLevel::CRITICAL)
            ->with(['currentLevel', 'department', 'latestRiskAssessment'])
            ->get();
    }

    /**
     * Escalate ticket by risk
     *
     * @param  mixed  $assessor  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function escalateByRisk(Ticket $ticket, $assessor): void
    {
        if (! $ticket->currentLevel->can_escalate) {
            return;
        }

        $nextLevel = \AichaDigital\Laratickets\Models\TicketLevel::where('level', $ticket->currentLevel->level + 1)
            ->first();

        if (! $nextLevel) {
            return;
        }

        $this->escalationService->requestEscalation(
            $ticket,
            $nextLevel,
            'Automatic escalation due to critical risk assessment',
            $assessor,
            true
        );

        // Auto-approve since it's system-initiated
        /** @var \AichaDigital\Laratickets\Models\EscalationRequest|null $latestEscalation */
        $latestEscalation = $ticket->escalationRequests()
            ->where('is_automatic', true)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($latestEscalation) {
            $this->escalationService->autoApproveSystemEscalation($latestEscalation);
        }
    }

    /**
     * Get risk assessment statistics
     *
     * @return array<string, mixed>
     */
    public function getRiskStatistics(): array
    {
        return [
            'total_assessments' => RiskAssessment::count(),
            'by_level' => [
                'low' => RiskAssessment::where('risk_level', RiskLevel::LOW)->count(),
                'medium' => RiskAssessment::where('risk_level', RiskLevel::MEDIUM)->count(),
                'high' => RiskAssessment::where('risk_level', RiskLevel::HIGH)->count(),
                'critical' => RiskAssessment::where('risk_level', RiskLevel::CRITICAL)->count(),
            ],
            'active_high_risk' => Ticket::highRisk()->open()->count(),
            'active_critical' => Ticket::where('assessed_risk', RiskLevel::CRITICAL)->open()->count(),
        ];
    }

    /**
     * Get risk assessment history for a ticket
     *
     * @return Collection<int, RiskAssessment>
     */
    public function getTicketRiskHistory(Ticket $ticket): Collection
    {
        /** @var Collection<int, RiskAssessment> $assessments */
        $assessments = $ticket->riskAssessments()
            ->orderBy('created_at', 'desc')
            ->get();

        return $assessments;
    }
}
