<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $assessor_id
 * @property RiskLevel $risk_level
 * @property string $justification
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Ticket $ticket
 */
class RiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'assessor_id',
        'risk_level',
        'justification',
    ];

    protected $casts = [
        'risk_level' => RiskLevel::class,
    ];

    /**
     * @return BelongsTo<Ticket, RiskAssessment>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @param  Builder<RiskAssessment>  $query
     * @return Builder<RiskAssessment>
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('risk_level', RiskLevel::CRITICAL);
    }

    /**
     * @param  Builder<RiskAssessment>  $query
     * @return Builder<RiskAssessment>
     */
    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('risk_level', RiskLevel::HIGH);
    }

    /**
     * @param  Builder<RiskAssessment>  $query
     * @return Builder<RiskAssessment>
     */
    public function scopeHighOrCritical(Builder $query): Builder
    {
        return $query->whereIn('risk_level', [RiskLevel::HIGH, RiskLevel::CRITICAL]);
    }

    public function isCritical(): bool
    {
        return $this->risk_level === RiskLevel::CRITICAL;
    }

    public function shouldAutoEscalate(): bool
    {
        return $this->risk_level->shouldAutoEscalate();
    }
}
