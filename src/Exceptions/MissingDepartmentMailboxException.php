<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

use AichaDigital\Laratickets\Models\Department;
use RuntimeException;

/**
 * Thrown when recipient resolution needs to fall back to a department mailbox
 * but the department has no `mailbox_email` configured. Typed so the consumer
 * can distinguish this configuration gap from other runtime errors instead of
 * string-matching on a message.
 */
final class MissingDepartmentMailboxException extends RuntimeException
{
    public static function for(Department $department): self
    {
        return new self(sprintf(
            'Department [%s] (id %s) has no mailbox_email configured; cannot route ticket notifications to it.',
            $department->name,
            (string) $department->id,
        ));
    }
}
