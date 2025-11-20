<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Contracts;

use AichaDigital\Laratickets\Models\TicketLevel;
use Illuminate\Support\Collection;

interface UserCapabilityContract
{
    /**
     * Get the ticket level for a user
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function getUserLevel($user): ?TicketLevel;

    /**
     * Get departments assigned to a user
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     * @return Collection<int, \AichaDigital\Laratickets\Models\Department>
     */
    public function getUserDepartments($user): Collection;

    /**
     * Check if user can escalate to a specific level
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     */
    public function canUserEscalateTo($user, TicketLevel $targetLevel): bool;

    /**
     * Get tickets currently assigned to a user
     *
     * @param  mixed  $user  User model instance (type is configurable via config('laratickets.user.model'))
     * @return Collection<int, \AichaDigital\Laratickets\Models\Ticket>
     */
    public function getUserAssignedTickets($user): Collection;
}
