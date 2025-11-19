<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low Risk',
            self::MEDIUM => 'Medium Risk',
            self::HIGH => 'High Risk',
            self::CRITICAL => 'Critical Risk',
        };
    }

    public function shouldAutoEscalate(): bool
    {
        return $this === self::CRITICAL;
    }

    public function numericValue(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }
}
