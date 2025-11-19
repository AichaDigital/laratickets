<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Implementations;

use AichaDigital\Laratickets\Contracts\UserCapabilityContract;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Collection;

/**
 * Basic user capability implementation without external dependencies
 * Applications should override this with their own implementation
 */
class BasicUserCapabilityHandler implements UserCapabilityContract
{
    public function getUserLevel($user): ?TicketLevel
    {
        // Basic implementation: returns null (no level assigned)
        // Applications should override with actual level lookup
        return null;
    }

    public function getUserDepartments($user): Collection
    {
        // Basic implementation: returns empty collection
        // Applications should override with actual department lookup
        return collect([]);
    }

    public function canUserEscalateTo($user, TicketLevel $targetLevel): bool
    {
        // Basic implementation: all users can escalate to any level
        // Applications should override with level-based logic
        return true;
    }

    public function getUserAssignedTickets($user): Collection
    {
        $userId = $user->{config('laratickets.user.id_column', 'id')};

        return Ticket::whereHas('assignments', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->whereNull('completed_at');
        })->get();
    }
}
