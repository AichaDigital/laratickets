<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Events\AgentRated;
use AichaDigital\Laratickets\Events\TicketEvaluated;
use AichaDigital\Laratickets\Models\AgentRating;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAssignment;
use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    public function __construct(
        protected TicketAuthorizationContract $authorization
    ) {}

    /**
     * Evaluate a ticket
     *
     * @param  mixed  $evaluator  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function evaluateTicket(
        Ticket $ticket,
        $evaluator,
        float $score,
        ?string $comment = null
    ): TicketEvaluation {
        if (! config('laratickets.evaluation.enabled', true)) {
            throw new \RuntimeException('Evaluation system is disabled');
        }

        if (! $this->authorization->canEvaluateTicket($evaluator, $ticket)) {
            throw new \RuntimeException('User is not authorized to evaluate this ticket');
        }

        $this->validateScore($score);

        return DB::transaction(function () use ($ticket, $evaluator, $score, $comment) {
            // Check if user already evaluated
            $existing = TicketEvaluation::where('ticket_id', $ticket->id)
                ->where('evaluator_id', $evaluator->{config('laratickets.user.id_column', 'id')})
                ->first();

            if ($existing) {
                $existing->update(['score' => $score, 'comment' => $comment]);
                $evaluation = $existing;
            } else {
                $evaluation = TicketEvaluation::create([
                    'ticket_id' => $ticket->id,
                    'evaluator_id' => $evaluator->{config('laratickets.user.id_column', 'id')},
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
     * @param  mixed  $rater  Rater user model instance (type is configurable via config('laratickets.user.model'))
     */
    public function rateAgent(
        Ticket $ticket,
        $agent,
        $rater,
        float $score,
        ?string $comment = null
    ): AgentRating {
        if (! config('laratickets.evaluation.agent_rating_enabled', true)) {
            throw new \RuntimeException('Agent rating system is disabled');
        }

        if (! $this->authorization->canRateAgent($rater, $ticket, $agent)) {
            throw new \RuntimeException('User is not authorized to rate this agent');
        }

        $this->validateScore($score);

        // Verify agent participated in ticket
        $participated = $ticket->assignments()
            ->where('user_id', $agent->{config('laratickets.user.id_column', 'id')})
            ->exists();

        if (! $participated) {
            throw new \RuntimeException('Agent did not participate in this ticket');
        }

        return DB::transaction(function () use ($ticket, $agent, $rater, $score, $comment) {
            $agentId = $agent->{config('laratickets.user.id_column', 'id')};
            $raterId = $rater->{config('laratickets.user.id_column', 'id')};

            // Check if rater already rated this agent on this ticket
            $existing = AgentRating::where('ticket_id', $ticket->id)
                ->where('agent_id', $agentId)
                ->where('rater_id', $raterId)
                ->first();

            if ($existing) {
                $existing->update(['score' => $score, 'comment' => $comment]);
                $rating = $existing;
            } else {
                $rating = AgentRating::create([
                    'ticket_id' => $ticket->id,
                    'agent_id' => $agentId,
                    'rater_id' => $raterId,
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
        $agentId = $agent->{config('laratickets.user.id_column', 'id')};

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
        $agentId = $agent->{config('laratickets.user.id_column', 'id')};

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
