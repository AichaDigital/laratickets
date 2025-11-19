<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'assessor_id' => $this->assessor_id,
            'risk_level' => [
                'value' => $this->risk_level->value,
                'label' => $this->risk_level->label(),
                'numeric' => $this->risk_level->numericValue(),
            ],
            'justification' => $this->justification,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
