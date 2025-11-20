<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $agent_id
 * @property int $rater_id
 * @property float $score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Ticket $ticket
 */
class AgentRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'agent_id',
        'rater_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Ticket, AgentRating>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  Builder<AgentRating>  $query
     * @return Builder<AgentRating>
     */
    public function scopeForAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * @param  Builder<AgentRating>  $query
     * @return Builder<AgentRating>
     */
    public function scopeHighRated(Builder $query): Builder
    {
        return $query->where('score', '>=', 4.0);
    }

    /**
     * @param  Builder<AgentRating>  $query
     * @return Builder<AgentRating>
     */
    public function scopeLowRated(Builder $query): Builder
    {
        return $query->where('score', '<=', 2.0);
    }
}
