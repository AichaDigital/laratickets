<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\AgentRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgentRating
 */
class AgentRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'agent_id' => $this->agent_id,
            'rater_id' => $this->rater_id,
            'score' => (float) $this->score,
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
