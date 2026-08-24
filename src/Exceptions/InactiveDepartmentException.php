<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

use AichaDigital\Laratickets\Models\Department;

/**
 * Thrown when a ticket would be filed into — or moved into — a department
 * whose `active` flag is false, i.e. one the operator has taken out of service.
 *
 * Introduced in 1.2.0 (AID-1085). Before that release every write path accepted
 * a retired department, so a consumer that relies on the old behaviour can
 * restore it with `laratickets.departments.enforce_active = false` instead of
 * catching this. Consumers typically map it to HTTP 422.
 */
final class InactiveDepartmentException extends TicketException
{
    public static function for(Department $department): self
    {
        return new self(sprintf(
            'Department [%s] (id %s) is not active; tickets cannot be filed into a retired department. '
            .'Set laratickets.departments.enforce_active to false to restore the pre-1.2.0 behaviour.',
            $department->name,
            (string) $department->id,
        ));
    }

    public static function forId(int|string $departmentId): self
    {
        return new self(sprintf(
            'Department id %s is not an active department; tickets cannot be filed into a retired '
            .'or non-existent department. Set laratickets.departments.enforce_active to false to '
            .'restore the pre-1.2.0 behaviour.',
            (string) $departmentId,
        ));
    }
}
