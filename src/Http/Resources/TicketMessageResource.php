<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Http\Resources;

use AichaDigital\Laratickets\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'author_id' => $this->author_id,
            'author_role' => [
                'value' => $this->author_role?->value,
                'label' => $this->author_role?->label(),
            ],
            'visibility' => [
                'value' => $this->visibility?->value,
                'label' => $this->visibility?->label(),
            ],
            'body' => $this->body,
            'is_redacted' => $this->isRedacted(),
            'redacted_at' => $this->redacted_at?->toIso8601String(),
            'redacted_by' => $this->redacted_by,
            'redaction_reason' => $this->redaction_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
