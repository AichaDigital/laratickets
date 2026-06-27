<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Support;

/**
 * Non-human actor for domain operations triggered without a user in context:
 * timeout auto-escalation, queued jobs, console commands.
 *
 * Resolves to a null id (via {@see ActorId}) and is, in v1.0, explicitly
 * silent — it is not notifiable, so routing a notification "to the system"
 * simply yields no recipient. Replaces the previous
 * `(object) [id_column => null]` hack used by automatic escalation.
 */
final class SystemActor
{
    /**
     * Any id-column lookup on the system actor resolves to null.
     */
    public function __get(string $name): mixed
    {
        return null;
    }
}
