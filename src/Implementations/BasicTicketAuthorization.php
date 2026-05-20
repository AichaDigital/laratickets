<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Implementations;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\EscalationRequest;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAttachment;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Models\TicketMessage;

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

    /**
     * ADR-002 — Creator puede subir si ticket abierto (NEW/ASSIGNED/IN_PROGRESS).
     * Cualquier otro usuario (proxy: staff) puede subir sin restricción de estado.
     * Apps reales deben rebindar el contract si necesitan policy más estricta.
     *
     * @param  mixed  $user  User model instance
     */
    public function canAttachFile($user, Ticket $ticket): bool
    {
        $userId = $user->{config('laratickets.user.id_column', 'id')};
        $isCreator = $userId === $ticket->created_by;

        if ($isCreator) {
            return in_array($ticket->status, [
                TicketStatus::NEW,
                TicketStatus::ASSIGNED,
                TicketStatus::IN_PROGRESS,
            ], true);
        }

        // Cualquier otro user es asumido staff en la implementación basic.
        return true;
    }

    /**
     * ADR-002 — Creator del ticket o cualquier otro user (proxy staff).
     *
     * Atención: la basic implementation permite a cualquier user descargar
     * (igual que otros métodos basic devuelven `true` por defecto). Apps
     * con role system DEBEN rebindar este método para excluir clientes
     * ajenos al ticket.
     *
     * @param  mixed  $user  User model instance
     */
    public function canDownloadFile($user, TicketAttachment $attachment): bool
    {
        unset($user, $attachment);

        return true;
    }

    /**
     * ADR-002 — Solo el uploader puede borrar su propio attachment.
     *
     * @param  mixed  $user  User model instance
     */
    public function canDeleteAttachment($user, TicketAttachment $attachment): bool
    {
        $userId = $user->{config('laratickets.user.id_column', 'id')};

        return $userId === $attachment->uploader_id;
    }

    /**
     * ADR-003
     *
     * - `CLIENT` role: only the ticket creator can post.
     * - `STAFF` role: any non-creator user can post.
     * - Terminal hard states (`CLOSED`, `CANCELLED`) are denied.
     * - `RESOLVED` is allowed, with no automatic reopen.
     */
    public function canPostMessage($user, Ticket $ticket, MessageAuthorRole $role): bool
    {
        if (in_array($ticket->status, [TicketStatus::CLOSED, TicketStatus::CANCELLED], true)) {
            return false;
        }

        $userId = $user->{config('laratickets.user.id_column', 'id')};
        $isCreator = $userId === $ticket->created_by;

        return match ($role) {
            MessageAuthorRole::CLIENT => $isCreator,
            MessageAuthorRole::STAFF => ! $isCreator,
        };
    }

    /**
     * ADR-003
     *
     * Conservative default: internal messages are hidden by default.
     */
    public function canViewInternalMessages($user, Ticket $ticket): bool
    {
        unset($user, $ticket);

        return false;
    }

    /**
     * ADR-003
     *
     * Public messages are always visible.
     * Internal messages require internal visibility entitlement.
     */
    public function canViewMessage($user, TicketMessage $message): bool
    {
        if ($message->visibility === MessageVisibility::PUBLIC) {
            return true;
        }

        return $this->canViewInternalMessages($user, $message->ticket);
    }

    /**
     * ADR-003
     *
     * Sensitive action; disabled in basic implementation.
     */
    public function canRedactMessage($user, TicketMessage $message): bool
    {
        unset($user, $message);

        return false;
    }
}
