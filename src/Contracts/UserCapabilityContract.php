<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Contracts;

use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Collection;

interface UserCapabilityContract
{
    /**
     * Get the ticket level for a user
     */
    public function getUserLevel($user): ?TicketLevel;

    /**
     * Get departments assigned to a user
     */
    public function getUserDepartments($user): Collection;

    /**
     * Check if user can escalate to a specific level
     */
    public function canUserEscalateTo($user, TicketLevel $targetLevel): bool;

    /**
     * Get tickets currently assigned to a user
     */
    public function getUserAssignedTickets($user): Collection;
}
