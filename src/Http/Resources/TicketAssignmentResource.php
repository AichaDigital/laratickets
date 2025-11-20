<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\TicketAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketAssignment
 */
class TicketAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'individual_rating' => $this->individual_rating,
            'is_active' => $this->isActive(),
        ];
    }
}
