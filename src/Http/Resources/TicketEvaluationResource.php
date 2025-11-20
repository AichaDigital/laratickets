<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\TicketEvaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketEvaluation
 */
class TicketEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'evaluator_id' => $this->evaluator_id,
            'score' => (float) $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
