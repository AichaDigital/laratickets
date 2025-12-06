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
 * @property mixed $user_id User ID (type depends on config)
 * @property \Illuminate\Support\Carbon|null $assigned_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property float|null $individual_rating
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read \Illuminate\Database\Eloquent\Model $user
 */
class TicketAssignment extends Model
{
    use HasFactory;
    use HasUserRelation;

    /**
     * User columns for HasUserRelation trait.
     *
     * @var array<string>
     */
    protected array $userColumns = ['user_id'];

    protected $fillable = [
        'ticket_id',
        'user_id',
        'assigned_at',
        'completed_at',
        'individual_rating',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'individual_rating' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Ticket, TicketAssignment>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  Builder<TicketAssignment>  $query
     * @return Builder<TicketAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * @param  Builder<TicketAssignment>  $query
     * @return Builder<TicketAssignment>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function isActive(): bool
    {
        return $this->completed_at === null;
    }

    public function complete(): void
    {
        $this->update(['completed_at' => now()]);
    }
}
