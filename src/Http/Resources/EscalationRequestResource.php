<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\EscalationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EscalationRequest
 */
class EscalationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'from_level' => new TicketLevelResource($this->whenLoaded('fromLevel')),
            'to_level' => new TicketLevelResource($this->whenLoaded('toLevel')),
            'requester_id' => $this->requester_id,
            'approver_id' => $this->approver_id,
            'justification' => $this->justification,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'is_automatic' => $this->is_automatic,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
