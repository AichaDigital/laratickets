<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'user_priority' => [
                'value' => $this->user_priority->value,
                'label' => $this->user_priority->label(),
                'numeric' => $this->user_priority->numericValue(),
            ],
            'assessed_risk' => $this->assessed_risk ? [
                'value' => $this->assessed_risk->value,
                'label' => $this->assessed_risk->label(),
                'numeric' => $this->assessed_risk->numericValue(),
            ] : null,
            'current_level' => new TicketLevelResource($this->whenLoaded('currentLevel')),
            'requested_level' => new TicketLevelResource($this->whenLoaded('requestedLevel')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'assignments' => TicketAssignmentResource::collection($this->whenLoaded('assignments')),
            'global_score' => $this->global_score,
            'total_evaluations' => $this->total_evaluations,
            'estimated_deadline' => $this->estimated_deadline?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_open' => $this->isOpen(),
            'is_overdue' => $this->isOverdue(),
        ];
    }
}
