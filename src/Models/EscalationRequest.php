<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

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
    /** @use HasFactory<*> */
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

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'from_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'to_level_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeAutomatic($query)
    {
        return $query->where('is_automatic', true);
    }

    public function scopeManual($query)
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

    public function approve($approverId): void
    {
        $this->update([
            'status' => 'approved',
            'approver_id' => $approverId,
            'resolved_at' => now(),
        ]);
    }

    public function reject($approverId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approver_id' => $approverId,
            'rejection_reason' => $reason,
            'resolved_at' => now(),
        ]);
    }
}
