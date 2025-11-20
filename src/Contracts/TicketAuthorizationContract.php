<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Contracts;

use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;

interface TicketAuthorizationContract
{
    // Basic ticket operations
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canViewTicket($user, Ticket $ticket): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canCreateTicket($user): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canUpdateTicket($user, Ticket $ticket): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canDeleteTicket($user, Ticket $ticket): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canCloseTicket($user, Ticket $ticket): bool;

    // Level-specific operations
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAccessLevel($user, TicketLevel $level): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssignToLevel($user, TicketLevel $level): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canRequestEscalation($user, Ticket $ticket): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canApproveEscalation($user, EscalationRequest $request): bool;

    // Evaluation operations
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canEvaluateTicket($user, Ticket $ticket): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     * @param  mixed  $agent  Agent user model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canRateAgent($user, Ticket $ticket, $agent): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssessRisk($user, Ticket $ticket): bool;

    // Department operations
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAccessDepartment($user, Department $department): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssignToDepartment($user, Department $department): bool;

    // Administrative operations
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canManageLevels($user): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canManageDepartments($user): bool;

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canViewStatistics($user): bool;
}
