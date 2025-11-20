<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $level
 * @property string $name
 * @property string|null $description
 * @property bool $can_escalate
 * @property bool $can_assess_risk
 * @property int $default_sla_hours
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TicketLevel extends Model
{
    /** @use HasFactory<*> */
    use HasFactory;

    protected $fillable = [
        'level',
        'name',
        'description',
        'can_escalate',
        'can_assess_risk',
        'default_sla_hours',
        'active',
    ];

    protected $casts = [
        'level' => 'integer',
        'can_escalate' => 'boolean',
        'can_assess_risk' => 'boolean',
        'default_sla_hours' => 'integer',
        'active' => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'current_level_id');
    }

    public function pendingEscalationTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requested_level_id');
    }

    public function escalationsFrom(): HasMany
    {
        return $this->hasMany(EscalationRequest::class, 'from_level_id');
    }

    public function escalationsTo(): HasMany
    {
        return $this->hasMany(EscalationRequest::class, 'to_level_id');
    }

    public function isHigherThan(TicketLevel $other): bool
    {
        return $this->level > $other->level;
    }

    public function canEscalateTo(TicketLevel $target): bool
    {
        return $this->can_escalate && $target->level > $this->level;
    }
}
