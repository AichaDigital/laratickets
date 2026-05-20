<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

enum MessageVisibility: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public',
            self::INTERNAL => 'Internal',
        };
    }
}
