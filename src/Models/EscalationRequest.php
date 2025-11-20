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
 * @property int $from_level_id
 * @property int $to_level_id
 * @property int $requester_id
 * @property int|null $approver_id
 * @property string $justification
 * @property string $status
 * @property string|null $rejection_reason
 * @property bool $is_automatic
 * @property \Illuminate\Support\Carbon|null $requested_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read TicketLevel $fromLevel
 * @property-read TicketLevel $toLevel
 */
class EscalationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'from_level_id',
        'to_level_id',
        'requester_id',
        'approver_id',
        'justification',
        'status',
        'rejection_reason',
        'is_automatic',
        'requested_at',
        'resolved_at',
    ];

    protected $casts = [
        'is_automatic' => 'boolean',
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Ticket, EscalationRequest>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<TicketLevel, EscalationRequest>
     */
    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'from_level_id');
    }

    /**
     * @return BelongsTo<TicketLevel, EscalationRequest>
     */
    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'to_level_id');
    }

    /**
     * @param  Builder<EscalationRequest>  $query
     * @return Builder<EscalationRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<EscalationRequest>  $query
     * @return Builder<EscalationRequest>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * @param  Builder<EscalationRequest>  $query
     * @return Builder<EscalationRequest>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * @param  Builder<EscalationRequest>  $query
     * @return Builder<EscalationRequest>
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('is_automatic', true);
    }

    /**
     * @param  Builder<EscalationRequest>  $query
     * @return Builder<EscalationRequest>
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('is_automatic', false);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approve(int $approverId): void
    {
        $this->update([
            'status' => 'approved',
            'approver_id' => $approverId,
            'resolved_at' => now(),
        ]);
    }

    public function reject(int $approverId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approver_id' => $approverId,
            'rejection_reason' => $reason,
            'resolved_at' => now(),
        ]);
    }
}
