<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Models\Department;

it('can create a department', function () {
    $department = Department::create([
        'name' => 'Technical',
        'description' => 'Technical support department',
        'active' => true,
    ]);

    expect($department)->toBeInstanceOf(Department::class)
        ->and($department->name)->toBe('Technical')
        ->and($department->description)->toBe('Technical support department')
        ->and($department->active)->toBeTrue();
});

it('can scope active departments', function () {
    Department::create([
        'name' => 'Active Dept',
        'active' => true,
    ]);

    Department::create([
        'name' => 'Inactive Dept',
        'active' => false,
    ]);

    $activeDepts = Department::active()->get();

    expect($activeDepts)->toHaveCount(1)
        ->and($activeDepts->first()->name)->toBe('Active Dept');
});
