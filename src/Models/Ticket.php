<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Concerns\HasUserRelation;
use AichaDigital\Laratickets\Concerns\HasUuid;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\RiskLevel;
use AichaDigital\Laratickets\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id UUID v7 primary key
 * @property string $subject
 * @property string $description
 * @property TicketStatus $status
 * @property Priority $user_priority
 * @property RiskLevel|null $assessed_risk
 * @property int $current_level_id
 * @property int|null $requested_level_id
 * @property int $department_id
 * @property mixed $created_by User ID (type depends on config)
 * @property mixed|null $resolved_by User ID (type depends on config)
 * @property float|null $global_score
 * @property int $total_evaluations
 * @property \Illuminate\Support\Carbon|null $estimated_deadline
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read TicketLevel $currentLevel
 * @property-read TicketLevel|null $requestedLevel
 * @property-read Department $department
 * @property-read \Illuminate\Database\Eloquent\Model $creator
 * @property-read \Illuminate\Database\Eloquent\Model|null $resolver
 */
class Ticket extends Model
{
    use HasFactory;
    use HasUserRelation;
    use HasUuid;
    use SoftDeletes;

    /**
     * User columns for HasUserRelation trait.
     *
     * @var array<string>
     */
    protected array $userColumns = ['created_by', 'resolved_by'];

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            // Auto-assign level 1 if not set
            if (empty($ticket->current_level_id)) {
                $defaultLevel = TicketLevel::where('level', 1)->where('active', true)->first();
                if ($defaultLevel) {
                    $ticket->current_level_id = $defaultLevel->id;
                }
            }

            // Auto-assign creator if authenticated and not set
            if (empty($ticket->created_by) && auth()->check()) {
                $ticket->created_by = auth()->id();
            }
        });
    }

    protected $fillable = [
        'subject',
        'description',
        'status',
        'user_priority',
        'assessed_risk',
        'current_level_id',
        'requested_level_id',
        'department_id',
        'created_by',
        'resolved_by',
        'global_score',
        'total_evaluations',
        'estimated_deadline',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'user_priority' => Priority::class,
        'assessed_risk' => RiskLevel::class,
        'global_score' => 'decimal:2',
        'total_evaluations' => 'integer',
        'estimated_deadline' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Relationships
    /**
     * @return BelongsTo<TicketLevel, Ticket>
     */
    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'current_level_id');
    }

    /**
     * @return BelongsTo<TicketLevel, Ticket>
     */
    public function requestedLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'requested_level_id');
    }

    /**
     * @return BelongsTo<Department, Ticket>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<TicketAssignment, Ticket>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    /**
     * @return HasMany<TicketAssignment, Ticket>
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)
            ->whereNull('completed_at');
    }

    /**
     * @return HasMany<EscalationRequest, Ticket>
     */
    public function escalationRequests(): HasMany
    {
        return $this->hasMany(EscalationRequest::class);
    }

    /**
     * @return HasMany<EscalationRequest, Ticket>
     */
    public function pendingEscalations(): HasMany
    {
        return $this->hasMany(EscalationRequest::class)
            ->where('status', 'pending');
    }

    /**
     * @return HasMany<TicketEvaluation, Ticket>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(TicketEvaluation::class);
    }

    /**
     * @return HasMany<AgentRating, Ticket>
     */
    public function agentRatings(): HasMany
    {
        return $this->hasMany(AgentRating::class);
    }

    /**
     * @return HasMany<RiskAssessment, Ticket>
     */
    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }

    /**
     * @return HasMany<RiskAssessment, Ticket>
     */
    public function latestRiskAssessment(): HasMany
    {
        return $this->hasMany(RiskAssessment::class)
            ->latest();
    }

    // Scopes
    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::NEW,
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS,
            TicketStatus::ESCALATION_REQUESTED,
            TicketStatus::ESCALATED,
        ]);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TicketStatus::RESOLVED,
            TicketStatus::CLOSED,
            TicketStatus::CANCELLED,
        ]);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeByLevel(Builder $query, int $level): Builder
    {
        return $query->whereHas('currentLevel', function ($q) use ($level) {
            $q->where('level', $level);
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeByDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('estimated_deadline')
            ->where('estimated_deadline', '<', now())
            ->open();
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('assessed_risk', RiskLevel::HIGH)
            ->orWhere('assessed_risk', RiskLevel::CRITICAL);
    }

    // Helper methods
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isClosed(): bool
    {
        return $this->status->isClosed();
    }

    public function isOverdue(): bool
    {
        return $this->estimated_deadline && $this->estimated_deadline->isPast() && $this->isOpen();
    }

    public function hasActiveEscalation(): bool
    {
        return $this->status === TicketStatus::ESCALATION_REQUESTED;
    }

    public function canEscalate(): bool
    {
        return $this->isOpen()
            && ! $this->hasActiveEscalation()
            && $this->currentLevel->can_escalate;
    }

    public function updateGlobalScore(): void
    {
        $avgScore = $this->evaluations()->avg('score');
        $this->update([
            'global_score' => $avgScore,
            'total_evaluations' => $this->evaluations()->count(),
        ]);
    }
}
