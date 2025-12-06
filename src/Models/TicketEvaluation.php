<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Concerns\HasUserRelation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ticket_id UUID reference to ticket
 * @property mixed $evaluator_id User ID (type depends on config)
 * @property float $score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read \Illuminate\Database\Eloquent\Model $evaluator
 */
class TicketEvaluation extends Model
{
    use HasFactory;
    use HasUserRelation;

    /**
     * User columns for HasUserRelation trait.
     *
     * @var array<string>
     */
    protected array $userColumns = ['evaluator_id'];

    protected $fillable = [
        'ticket_id',
        'evaluator_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Ticket, TicketEvaluation>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  Builder<TicketEvaluation>  $query
     * @return Builder<TicketEvaluation>
     */
    public function scopeHighRated(Builder $query): Builder
    {
        return $query->where('score', '>=', 4.0);
    }

    /**
     * @param  Builder<TicketEvaluation>  $query
     * @return Builder<TicketEvaluation>
     */
    public function scopeLowRated(Builder $query): Builder
    {
        return $query->where('score', '<=', 2.0);
    }
}
