<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

/**
 * Quién subió un attachment (ADR-002).
 *
 * String-backed (NO MySQL ENUM, per project standard). La autorización
 * por estado del ticket se basa en este valor combinado con quien hace
 * la operación.
 */
enum AttachmentUploaderRole: string
{
    case CLIENT = 'client';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Cliente',
            self::STAFF => 'Soporte',
        };
    }
}
