<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Events\AgentRated;
use AichaDigital\Laratickets\Events\TicketEvaluated;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\AgentRating;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketEvaluation;
use AichaDigital\Laratickets\Support\ActorId;
use Illuminate\Support\Facades\DB;

/**
 * @experimental Opcional-experimental (ADR-004): off by default and outside the
 *               v1.0 semver promise — may change or be removed without a major.
 */
class EvaluationService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    /**
     * Evaluate a ticket
     *
     * @param  mixed  $by  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function evaluateTicket(
        Ticket $ticket,
        $by,
        float $score,
        ?string $comment = null
    ): TicketEvaluation {
        if (! config('laratickets.evaluation.enabled', true)) {
            throw new TicketStateException('Evaluation system is disabled');
        }

        if (! $this->authorization->canEvaluateTicket($by, $ticket)) {
            throw new TicketAuthorizationException('User is not authorized to evaluate this ticket');
        }

        $this->validateScore($score);

        return DB::transaction(function () use ($ticket, $by, $score, $comment) {
            // Check if user already evaluated
            $existing = TicketEvaluation::where('ticket_id', $ticket->id)
                ->where('evaluator_id', ActorId::of($by))
                ->first();

            if ($existing) {
                $existing->update(['score' => $score, 'comment' => $comment]);
                $evaluation = $existing;
            } else {
                $evaluation = TicketEvaluation::create([
                    'ticket_id' => $ticket->id,
                    'evaluator_id' => ActorId::of($by),
                    'score' => $score,
                    'comment' => $comment,
                ]);
            }

            // Update ticket global score
            $ticket->updateGlobalScore();

            event(new TicketEvaluated($evaluation->load('ticket')));

            return $evaluation;
        });
    }

    /**
     * Rate an agent
     *
     * @param  mixed  $agent  Agent user model instance (type is configurable via config('laratickets.user.model'))
     * @param  mixed  $by  Rater user model instance (type is configurable via config('laratickets.user.model'))
     */
    public function rateAgent(
        Ticket $ticket,
        $agent,
        $by,
        float $score,
        ?string $comment = null
    ): AgentRating {
        if (! config('laratickets.evaluation.agent_rating_enabled', true)) {
            throw new TicketStateException('Agent rating system is disabled');
        }

        if (! $this->authorization->canRateAgent($by, $ticket, $agent)) {
            throw new TicketAuthorizationException('User is not authorized to rate this agent');
        }

        $this->validateScore($score);

        // Verify agent participated in ticket
        $participated = $ticket->assignments()
            ->where('user_id', ActorId::of($agent))
            ->exists();

        if (! $participated) {
            throw new TicketStateException('Agent did not participate in this ticket');
        }

        return DB::transaction(function () use ($ticket, $agent, $by, $score, $comment) {
            $agentId = ActorId::of($agent);
            $byId = ActorId::of($by);

            // Check if rater already rated this agent on this ticket
            $existing = AgentRating::where('ticket_id', $ticket->id)
                ->where('agent_id', $agentId)
                ->where('rater_id', $byId)
                ->first();

            if ($existing) {
                $existing->update(['score' => $score, 'comment' => $comment]);
                $rating = $existing;
            } else {
                $rating = AgentRating::create([
                    'ticket_id' => $ticket->id,
                    'agent_id' => $agentId,
                    'rater_id' => $byId,
                    'score' => $score,
                    'comment' => $comment,
                ]);
            }

            // Update assignment individual rating
            $assignment = $ticket->assignments()
                ->where('user_id', $agentId)
                ->first();

            if ($assignment) {
                $avgRating = AgentRating::where('ticket_id', $ticket->id)
                    ->where('agent_id', $agentId)
                    ->avg('score');

                $assignment->update(['individual_rating' => $avgRating]);
            }

            event(new AgentRated($rating->load('ticket')));

            return $rating;
        });
    }

    /**
     * Calculate average rating for an agent
     *
     * @param  mixed  $agent  Agent user model instance (type is configurable via config('laratickets.user.model'))
     */
    public function calculateAgentAverageRating($agent): float
    {
        $agentId = ActorId::of($agent);

        return AgentRating::where('agent_id', $agentId)
            ->avg('score') ?? 0.0;
    }

    /**
     * Get ticket evaluation statistics
     *
     * @return array<string, mixed>
     */
    public function getTicketStatistics(): array
    {
        return [
            'total_evaluations' => TicketEvaluation::count(),
            'average_score' => TicketEvaluation::avg('score') ?? 0.0,
            'high_rated_count' => TicketEvaluation::highRated()->count(),
            'low_rated_count' => TicketEvaluation::lowRated()->count(),
            'total_agent_ratings' => AgentRating::count(),
            'average_agent_score' => AgentRating::avg('score') ?? 0.0,
        ];
    }

    /**
     * Get statistics for an agent
     *
     * @param  mixed  $agent  Agent user model instance (type is configurable via config('laratickets.user.model'))
     * @return array<string, mixed>
     */
    public function getAgentStatistics($agent): array
    {
        $agentId = ActorId::of($agent);

        return [
            'total_ratings' => AgentRating::forAgent($agentId)->count(),
            'average_score' => $this->calculateAgentAverageRating($agent),
            'high_ratings' => AgentRating::forAgent($agentId)->highRated()->count(),
            'low_ratings' => AgentRating::forAgent($agentId)->lowRated()->count(),
            'tickets_participated' => TicketAssignment::where('user_id', $agentId)->count(),
        ];
    }

    protected function validateScore(float $score): void
    {
        $min = config('laratickets.evaluation.min_score', 1.0);
        $max = config('laratickets.evaluation.max_score', 5.0);

        if ($score < $min || $score > $max) {
            throw new \InvalidArgumentException("Score must be between {$min} and {$max}");
        }
    }
}
