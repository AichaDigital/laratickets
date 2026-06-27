<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketException;
use AichaDigital\Laratickets\Exceptions\TicketMessageRejected;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Services\TicketMessageService;
use AichaDigital\Laratickets\Services\TicketService;
use AichaDigital\Laratickets\Tests\TestCase;

/**
 * Contract: v1.0 ships a typed exception hierarchy rooted at TicketException,
 * which itself extends \RuntimeException so existing `catch (\RuntimeException)`
 * call sites keep working.
 */
beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create(['name' => 'Technical', 'active' => true]);

    $this->user = new class
    {
        public string $id = TestCase::USER_UUID_1;
    };

    $this->ticket = Ticket::create([
        'subject' => 'Support',
        'description' => 'Need help',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);
});

describe('typed exception hierarchy', function () {
    it('createTicket throws TicketAuthorizationException when actor cannot create', function () {
        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canCreateTicket')->andReturn(false);
        $service = new TicketService($auth);

        expect(fn () => $service->createTicket([
            'subject' => 'x',
            'description' => 'y',
            'department_id' => $this->department->id,
        ], $this->user))->toThrow(TicketAuthorizationException::class);
    });

    it('typed exceptions stay catchable as RuntimeException (back-compat)', function () {
        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canCreateTicket')->andReturn(false);
        $service = new TicketService($auth);

        $caught = null;
        try {
            $service->createTicket([
                'subject' => 'x',
                'description' => 'y',
                'department_id' => $this->department->id,
            ], $this->user);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        expect($caught)
            ->toBeInstanceOf(TicketAuthorizationException::class)
            ->and($caught)->toBeInstanceOf(TicketException::class)
            ->and($caught->getMessage())->toContain('not authorized');
    });

    it('post throws TicketMessageRejected on empty body', function () {
        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canPostMessage')->andReturn(true);
        $service = new TicketMessageService($auth);

        expect(fn () => $service->post($this->ticket, $this->user, '   ', MessageAuthorRole::CLIENT))
            ->toThrow(TicketMessageRejected::class);
    });

    it('post throws TicketMessageRejected exposing the max length on too-long body', function () {
        config()->set('laratickets.messages.max_body_length', 5);

        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $auth->shouldReceive('canPostMessage')->andReturn(true);
        $service = new TicketMessageService($auth);

        try {
            $service->post($this->ticket, $this->user, '123456', MessageAuthorRole::CLIENT);
            throw new LogicException('expected TicketMessageRejected');
        } catch (TicketMessageRejected $e) {
            expect($e->maxLength())->toBe(5);
        }
    });

    it('post throws TicketStateException when messages are disabled', function () {
        config()->set('laratickets.messages.enabled', false);

        $auth = Mockery::mock(TicketAuthorizationContract::class);
        $service = new TicketMessageService($auth);

        expect(fn () => $service->post($this->ticket, $this->user, 'hi', MessageAuthorRole::CLIENT))
            ->toThrow(TicketStateException::class);
    });
});
