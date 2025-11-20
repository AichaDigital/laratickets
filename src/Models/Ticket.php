<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Models;

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\RiskLevel;
use AichaDigital\Laratickets\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $subject
 * @property string $description
 * @property TicketStatus $status
 * @property Priority $user_priority
 * @property RiskLevel|null $assessed_risk
 * @property int $current_level_id
 * @property int|null $requested_level_id
 * @property int $department_id
 * @property int $created_by
 * @property int|null $resolved_by
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
 */
class Ticket extends Model
{
    /** @use HasFactory<*> */
    use HasFactory, SoftDeletes;

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
    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'current_level_id');
    }

    public function requestedLevel(): BelongsTo
    {
        return $this->belongsTo(TicketLevel::class, 'requested_level_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)
            ->whereNull('completed_at');
    }

    public function escalationRequests(): HasMany
    {
        return $this->hasMany(EscalationRequest::class);
    }

    public function pendingEscalations(): HasMany
    {
        return $this->hasMany(EscalationRequest::class)
            ->where('status', 'pending');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TicketEvaluation::class);
    }

    public function agentRatings(): HasMany
    {
        return $this->hasMany(AgentRating::class);
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function latestRiskAssessment(): HasMany
    {
        return $this->hasMany(RiskAssessment::class)
            ->latest();
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            TicketStatus::NEW,
            TicketStatus::ASSIGNED,
            TicketStatus::IN_PROGRESS,
            TicketStatus::ESCALATION_REQUESTED,
            TicketStatus::ESCALATED,
        ]);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', [
            TicketStatus::RESOLVED,
            TicketStatus::CLOSED,
            TicketStatus::CANCELLED,
        ]);
    }

    public function scopeByLevel($query, int $level)
    {
        return $query->whereHas('currentLevel', function ($q) use ($level) {
            $q->where('level', $level);
        });
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('estimated_deadline')
            ->where('estimated_deadline', '<', now())
            ->open();
    }

    public function scopeHighRisk($query)
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
