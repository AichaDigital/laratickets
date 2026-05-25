<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

use AichaDigital\Laratickets\Models\Department;
use RuntimeException;

/**
 * Thrown when recipient resolution needs a department fallback but the
 * department has neither `head_user_id` nor `mailbox_email` configured.
 * Typed so the consumer can distinguish this configuration gap from other
 * runtime errors instead of string-matching on a message.
 *
 * Class name kept for backwards compatibility with v0.6.x consumers that
 * catch this exception; semantics widened in v0.7.0 to cover both routes.
 */
final class MissingDepartmentMailboxException extends RuntimeException
{
    public static function for(Department $department): self
    {
        return new self(sprintf(
            'Department [%s] (id %s) has neither head_user_id nor mailbox_email configured; cannot route ticket notifications to it.',
            $department->name,
            (string) $department->id,
        ));
    }
}
