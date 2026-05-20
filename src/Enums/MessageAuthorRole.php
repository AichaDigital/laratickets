<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

enum MessageAuthorRole: string
{
    case CLIENT = 'client';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Client',
            self::STAFF => 'Staff',
        };
    }
}
