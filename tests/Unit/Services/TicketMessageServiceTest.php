<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Events\TicketMessagePosted;
use AichaDigital\Laratickets\Implementations\BasicTicketAuthorization;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Models\TicketMessage;
use AichaDigital\Laratickets\Services\TicketMessageService;
use AichaDigital\Laratickets\Tests\TestCase;
use Illuminate\Support\Facades\Event;

/**
 * Tests TicketMessageService (ADR-003).
 */
beforeEach(function () {
    Event::fake([TicketMessagePosted::class]);

    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create([
        'name' => 'Technical',
        'active' => true,
    ]);

    $this->ticket = Ticket::create([
        'subject' => 'Support question',
        'description' => 'Need help',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);

    $this->client = new class
    {
        public string $id = TestCase::USER_UUID_1;
    };

    $this->staff = new class
    {
        public string $id = TestCase::USER_UUID_2;
    };

    $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
    $this->authorization->shouldReceive('canPostMessage')->andReturn(true)->byDefault();
    $this->authorization->shouldReceive('canViewInternalMessages')->andReturn(false)->byDefault();
    $this->authorization->shouldReceive('canRedactMessage')->andReturn(true)->byDefault();

    $this->service = new TicketMessageService($this->authorization);
});

describe('TicketMessageService::post', function () {
    it('persists a public message and dispatches event', function () {
        $message = $this->service->post(
            $this->ticket,
            $this->client,
            '  This is a message with spaces.  ',
            MessageAuthorRole::CLIENT,
        );

        expect($message)
            ->toBeInstanceOf(TicketMessage::class)
            ->and($message->ticket_id)->toBe($this->ticket->id)
            ->and($message->author_id)->toBe(TestCase::USER_UUID_1)
            ->and($message->author_role)->toBe(MessageAuthorRole::CLIENT)
            ->and($message->visibility)->toBe(MessageVisibility::PUBLIC)
            ->and($message->body)->toBe('This is a message with spaces.');

        Event::assertDispatched(TicketMessagePosted::class);
    });

    it('rejects when messages are disabled', function () {
        config()->set('laratickets.messages.enabled', false);

        expect(fn () => $this->service->post($this->ticket, $this->client, 'hello', MessageAuthorRole::CLIENT))
            ->toThrow(RuntimeException::class, 'disabled');
    });

    it('rejects empty body', function () {
        expect(fn () => $this->service->post($this->ticket, $this->client, '   ', MessageAuthorRole::CLIENT))
            ->toThrow(RuntimeException::class, 'empty');
    });

    it('rejects messages above max length', function () {
        config()->set('laratickets.messages.max_body_length', 5);

        expect(fn () => $this->service->post($this->ticket, $this->client, '123456', MessageAuthorRole::CLIENT))
            ->toThrow(RuntimeException::class, 'exceeds max length');
    });

    it('respects authorization from contract', function () {
        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canPostMessage')->andReturn(false);
        $svc = new TicketMessageService($this->authorization);

        expect(fn () => $svc->post($this->ticket, $this->client, 'hello', MessageAuthorRole::CLIENT))
            ->toThrow(RuntimeException::class, 'not authorized');
    });

    it('prevents posting on hard-closed tickets in Basic policy', function () {
        $svc = new TicketMessageService(new BasicTicketAuthorization);
        $closed = Ticket::create([
            'subject' => 'Closed case',
            'description' => 'already closed',
            'status' => TicketStatus::CLOSED,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => TestCase::USER_UUID_1,
        ]);

        expect(fn () => $svc->post($closed, $this->client, 'hello', MessageAuthorRole::CLIENT))
            ->toThrow(RuntimeException::class, 'not authorized');

        $resolved = Ticket::create([
            'subject' => 'Resolved case',
            'description' => 'already resolved',
            'status' => TicketStatus::RESOLVED,
            'user_priority' => Priority::MEDIUM,
            'current_level_id' => $this->level->id,
            'department_id' => $this->department->id,
            'created_by' => TestCase::USER_UUID_1,
        ]);

        $message = $svc->post($resolved, $this->client, 'hello', MessageAuthorRole::CLIENT);
        expect($message->visibility)->toBe(MessageVisibility::PUBLIC);
    });
});

describe('TicketMessageService::listFor', function () {
    it('hides internal messages when internal visibility is not allowed', function () {
        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_1,
            'author_role' => MessageAuthorRole::CLIENT,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => 'public',
        ]);

        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_2,
            'author_role' => MessageAuthorRole::STAFF,
            'visibility' => MessageVisibility::INTERNAL,
            'body' => 'internal',
        ]);

        $list = $this->service->listFor($this->ticket, $this->client);

        expect($list)->toHaveCount(1);
        expect($list->first()->visibility)->toBe(MessageVisibility::PUBLIC);
    });

    it('returns internal messages when permission allows them', function () {
        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canViewInternalMessages')->andReturn(true);
        $svc = new TicketMessageService($this->authorization);

        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_1,
            'author_role' => MessageAuthorRole::CLIENT,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => 'public',
        ]);

        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_2,
            'author_role' => MessageAuthorRole::STAFF,
            'visibility' => MessageVisibility::INTERNAL,
            'body' => 'internal',
        ]);

        $list = $svc->listFor($this->ticket, $this->staff);

        expect($list)->toHaveCount(2);
    });
});

describe('TicketMessageService::redact', function () {
    it('redacts a message and keeps an audit trail', function () {
        $message = TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_1,
            'author_role' => MessageAuthorRole::CLIENT,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => 'sensitive data',
        ]);

        $redacted = $this->service->redact($message, $this->staff, 'contains sensitive data');

        expect($redacted->isRedacted())->toBeTrue()
            ->and($redacted->body)->toBe('[redacted]')
            ->and($redacted->redacted_by)->toBe(TestCase::USER_UUID_2)
            ->and($redacted->redaction_reason)->toBe('contains sensitive data');

        $redactedAgain = $this->service->redact($message->fresh(), $this->staff, 'should keep first reason');
        expect($redactedAgain->body)->toBe('[redacted]')
            ->and($redactedAgain->redaction_reason)->toBe('contains sensitive data');
    });

    it('rejects redaction without permission', function () {
        $this->authorization = Mockery::mock(TicketAuthorizationContract::class);
        $this->authorization->shouldReceive('canRedactMessage')->andReturn(false);
        $svc = new TicketMessageService($this->authorization);

        $message = TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'author_id' => TestCase::USER_UUID_1,
            'author_role' => MessageAuthorRole::CLIENT,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => 'sensitive data',
        ]);

        expect(fn () => $svc->redact($message, $this->client, 'nope'))
            ->toThrow(RuntimeException::class, 'not authorized');
    });
});
