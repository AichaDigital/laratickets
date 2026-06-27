<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);
});

it('does not backfill created_by from the authenticated user (domain is HTTP-agnostic)', function () {
    $authUser = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): string
        {
            return TestCase::USER_UUID_2;
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    $this->actingAs($authUser);

    $ticket = new Ticket([
        'subject' => 'No explicit creator',
        'description' => 'x',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'department_id' => $this->department->id,
        // created_by intentionally omitted — the domain must NOT read auth()
    ]);

    // Run the "creating" hook in isolation (no DB insert, so the created_by
    // NOT NULL constraint is irrelevant) to assert the domain ignores auth().
    Ticket::getEventDispatcher()->dispatch('eloquent.creating: '.Ticket::class, $ticket);

    expect(auth()->check())->toBeTrue()
        ->and($ticket->created_by)->toBeNull();
});

it('still auto-assigns the default level on creation', function () {
    $ticket = Ticket::create([
        'subject' => 'Auto level',
        'description' => 'x',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);

    expect($ticket->current_level_id)->toBe($this->level->id);
});
