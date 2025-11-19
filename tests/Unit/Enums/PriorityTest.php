<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;

it('has correct priority values', function () {
    expect(Priority::LOW->value)->toBe('low')
        ->and(Priority::MEDIUM->value)->toBe('medium')
        ->and(Priority::HIGH->value)->toBe('high')
        ->and(Priority::CRITICAL->value)->toBe('critical');
});

it('returns correct labels', function () {
    expect(Priority::LOW->label())->toBe('Low')
        ->and(Priority::MEDIUM->label())->toBe('Medium')
        ->and(Priority::HIGH->label())->toBe('High')
        ->and(Priority::CRITICAL->label())->toBe('Critical');
});

it('returns correct numeric values', function () {
    expect(Priority::LOW->numericValue())->toBe(1)
        ->and(Priority::MEDIUM->numericValue())->toBe(2)
        ->and(Priority::HIGH->numericValue())->toBe(3)
        ->and(Priority::CRITICAL->numericValue())->toBe(4);
});
