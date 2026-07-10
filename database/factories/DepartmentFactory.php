<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Database\Factories;

use AichaDigital\Laratickets\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `name` is UNIQUE; a random suffix gives intrinsic entropy without
        // Faker unique()'s per-process state that survives RefreshDatabase.
        return [
            'name' => fake()->company().' '.Str::upper(Str::random(6)),
            'description' => fake()->optional()->sentence(),
            'mailbox_email' => null,
            'head_user_id' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function withMailbox(): static
    {
        return $this->state(fn (): array => ['mailbox_email' => fake()->safeEmail()]);
    }

    public function withHead(): static
    {
        return $this->state(fn (): array => ['head_user_id' => (string) Str::uuid7()]);
    }
}
