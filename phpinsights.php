<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenTraits;
use NunoMaduro\PhpInsights\Domain\Metrics\Architecture\Classes;
use PHP_CodeSniffer\Standards\Generic\Sniffs\Files\LineLengthSniff;
use SlevomatCodingStandard\Sniffs\Classes\ForbiddenPublicPropertySniff;
use SlevomatCodingStandard\Sniffs\Commenting\UselessFunctionDocCommentSniff;
use SlevomatCodingStandard\Sniffs\ControlStructures\DisallowEmptySniff;
use SlevomatCodingStandard\Sniffs\Functions\FunctionLengthSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowArrayTypeHintSyntaxSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;

return [
    'preset' => 'laravel',

    'ide' => null,

    'exclude' => [
        'build',
        'vendor',
        'tests/Pest.php',
        'tests/TestCase.php',
    ],

    'add' => [
        Classes::class => [
            ForbiddenNormalClasses::class,
        ],
    ],

    'remove' => [
        // Allowed because we use strict types
        ForbiddenDefineFunctions::class,
        ForbiddenTraits::class,

        // We allow public properties in Models and DTOs
        ForbiddenPublicPropertySniff::class,

        // Sometimes we use empty constructors for DI
        DisallowEmptySniff::class,

        // We prefer readability over strict array syntax
        DisallowArrayTypeHintSyntaxSniff::class,

        // We prefer clarity over too many doc comments
        UselessFunctionDocCommentSniff::class,
    ],

    'config' => [
        ForbiddenNormalClasses::class => [
            'exclude' => [
                'src/Models',
                'src/Enums',
                'src/Http/Controllers',
                'src/Http/Middleware',
                'src/Http/Resources',
                'src/Http/Requests',
                'database/factories',
            ],
        ],

        LineLengthSniff::class => [
            'lineLimit' => 120,
            'absoluteLineLimit' => 160,
            'ignoreComments' => false,
        ],

        FunctionLengthSniff::class => [
            'maxLinesLength' => 30,
        ],

        ParameterTypeHintSniff::class => [
            'exclude' => [
                'src/Services',
                'src/Implementations',
            ],
        ],

        PropertyTypeHintSniff::class => [
            'exclude' => [
                'src/Models',
            ],
        ],

        ReturnTypeHintSniff::class => [
            'exclude' => [
                'src/Services',
            ],
        ],
    ],

    'requirements' => [
        'min-quality' => 85,
        'min-complexity' => 85,
        'min-architecture' => 85,
        'min-style' => 90,
        'disable-security-check' => false,
    ],

    'threads' => null,

    'timeout' => 60,
];
