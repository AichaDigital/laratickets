<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\Priority;
use AichaDigital\Laratickets\Enums\TicketEvent;
use AichaDigital\Laratickets\Enums\TicketStatus;
use AichaDigital\Laratickets\Models\Department;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketLevel;
use AichaDigital\Laratickets\Notifications\Recipient;
use AichaDigital\Laratickets\Notifications\RecipientResolver;
use AichaDigital\Laratickets\Tests\TestCase;

beforeEach(function () {
    $this->level = TicketLevel::create([
        'level' => 1,
        'name' => 'Level I',
        'can_escalate' => true,
        'can_assess_risk' => false,
        'default_sla_hours' => 24,
    ]);

    $this->department = Department::create([
        'name' => 'Technical',
        'mailbox_email' => 'tech@example.test',
        'active' => true,
    ]);

    $this->ticket = Ticket::create([
        'subject' => 'subject',
        'description' => 'body',
        'status' => TicketStatus::NEW,
        'user_priority' => Priority::MEDIUM,
        'current_level_id' => $this->level->id,
        'department_id' => $this->department->id,
        'created_by' => TestCase::USER_UUID_1,
    ]);
});

describe('Ticket::recipientsFor', function () {
    it('delegates to the resolver bound in the container', function () {
        $recipients = $this->ticket->recipientsFor(TicketEvent::STAFF_REPLIED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0])->toBeInstanceOf(Recipient::class)
            ->and($recipients[0]->userId)->toBe(TestCase::USER_UUID_1);
    });

    it('uses the container-bound resolver (swappable by the consumer)', function () {
        // Prove the binding is honored: swap in a stub resolver and check the
        // model surfaces what the stub returned.
        app()->instance(RecipientResolver::class, new class implements RecipientResolver
        {
            public function resolve($ticket, $event): array
            {
                return [Recipient::mailbox('overridden@example.test')];
            }
        });

        $recipients = $this->ticket->recipientsFor(TicketEvent::OPENED);

        expect($recipients)->toHaveCount(1)
            ->and($recipients[0]->isMailbox())->toBeTrue()
            ->and($recipients[0]->email)->toBe('overridden@example.test');
    });
});
