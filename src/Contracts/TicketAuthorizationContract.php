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
    public function canViewTicket($user, Ticket $ticket): bool;

    public function canCreateTicket($user): bool;

    public function canUpdateTicket($user, Ticket $ticket): bool;

    public function canDeleteTicket($user, Ticket $ticket): bool;

    public function canCloseTicket($user, Ticket $ticket): bool;

    // Level-specific operations
    public function canAccessLevel($user, TicketLevel $level): bool;

    public function canAssignToLevel($user, TicketLevel $level): bool;

    public function canRequestEscalation($user, Ticket $ticket): bool;

    public function canApproveEscalation($user, EscalationRequest $request): bool;

    // Evaluation operations
    public function canEvaluateTicket($user, Ticket $ticket): bool;

    public function canRateAgent($user, Ticket $ticket, $agent): bool;

    public function canAssessRisk($user, Ticket $ticket): bool;

    // Department operations
    public function canAccessDepartment($user, Department $department): bool;

    public function canAssignToDepartment($user, Department $department): bool;

    // Administrative operations
    public function canManageLevels($user): bool;

    public function canManageDepartments($user): bool;

    public function canViewStatistics($user): bool;
}
