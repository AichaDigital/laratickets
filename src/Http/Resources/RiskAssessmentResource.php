<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\RiskAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RiskAssessment
 */
class RiskAssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
