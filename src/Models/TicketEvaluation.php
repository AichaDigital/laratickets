<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'evaluator_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeHighRated($query)
    {
        return $query->where('score', '>=', 4.0);
    }

    public function scopeLowRated($query)
    {
        return $query->where('score', '<=', 2.0);
    }
}
