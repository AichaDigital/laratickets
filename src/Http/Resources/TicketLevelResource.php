<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketLevel
 */
class TicketLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'name' => $this->name,
            'description' => $this->description,
            'can_escalate' => $this->can_escalate,
            'can_assess_risk' => $this->can_assess_risk,
            'default_sla_hours' => $this->default_sla_hours,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
