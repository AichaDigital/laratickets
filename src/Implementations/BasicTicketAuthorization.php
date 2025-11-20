<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Implementations;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;

/**
 * Basic authorization implementation without external dependencies
 * Applications should override this with their own implementation
 */
class BasicTicketAuthorization implements TicketAuthorizationContract
{
    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canViewTicket($user, Ticket $ticket): bool
    {
        // Basic implementation: user can view tickets they created
        return $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canCreateTicket($user): bool
    {
        // Basic implementation: all users can create tickets
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canUpdateTicket($user, Ticket $ticket): bool
    {
        // Basic implementation: creator can update
        return $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canDeleteTicket($user, Ticket $ticket): bool
    {
        // Basic implementation: only creator can delete
        return $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by
            && $ticket->isOpen();
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canCloseTicket($user, Ticket $ticket): bool
    {
        // Basic implementation: creator or assigned agents can close
        $isCreator = $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by;
        $isAssigned = $ticket->assignments()
            ->where('user_id', $user->{config('laratickets.user.id_column', 'id')})
            ->exists();

        return $isCreator || $isAssigned;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAccessLevel($user, TicketLevel $level): bool
    {
        // Basic implementation: all users have access to all levels
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssignToLevel($user, TicketLevel $level): bool
    {
        // Basic implementation: all users can assign to any level
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canRequestEscalation($user, Ticket $ticket): bool
    {
        // Basic implementation: assigned agents can request escalation
        return $ticket->assignments()
            ->where('user_id', $user->{config('laratickets.user.id_column', 'id')})
            ->whereNull('completed_at')
            ->exists();
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canApproveEscalation($user, EscalationRequest $request): bool
    {
        // Basic implementation: any user can approve escalations
        // Applications should override this with proper logic
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canEvaluateTicket($user, Ticket $ticket): bool
    {
        // Basic implementation: only creator can evaluate when closed
        return $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by
            && $ticket->isClosed();
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     * @param  mixed  $agent  Agent user model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canRateAgent($user, Ticket $ticket, $agent): bool
    {
        // Basic implementation: creator can rate agents on closed tickets
        $isCreator = $user->{config('laratickets.user.id_column', 'id')} === $ticket->created_by;

        return $isCreator && $ticket->isClosed();
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssessRisk($user, Ticket $ticket): bool
    {
        // Basic implementation: any user can assess risk
        // Applications should override with level-based logic
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAccessDepartment($user, Department $department): bool
    {
        // Basic implementation: all users can access all departments
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canAssignToDepartment($user, Department $department): bool
    {
        // Basic implementation: all users can assign to any department
        return true;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canManageLevels($user): bool
    {
        // Basic implementation: no user can manage levels
        // Applications should override with admin logic
        return false;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canManageDepartments($user): bool
    {
        // Basic implementation: no user can manage departments
        // Applications should override with admin logic
        return false;
    }

    /**
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canViewStatistics($user): bool
    {
        // Basic implementation: all users can view statistics
        return true;
    }
}
