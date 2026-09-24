<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketAttachment;
use AichaDigital\Laratickets\Models\TicketMessage;
use AichaDigital\Laratickets\Tests\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Pins the UUID version emitted by HasUuid (AID-1420, STD-001).
 *
 * Str::orderedUuid() looks like the right call and is not: it is a v4 COMB
 * (time-ordered bytes, version nibble 4). Only Str::uuid7() satisfies the v7
 * key contract the package publishes, so these tests assert the version
 * nibble itself and go red on any regression to a v4 generator.
 */
it('emits UUID v7 keys for every model keyed by HasUuid', function () {
    $ticket = Ticket::factory()->create();

    $message = TicketMessage::create([
        'ticket_id' => $ticket->id,
        'author_id' => TestCase::USER_UUID_1,
        'author_role' => MessageAuthorRole::CLIENT,
        'body' => 'Hello',
    ]);

    $attachment = TicketAttachment::factory()->create(['ticket_id' => $ticket->id]);

    foreach ([$ticket, $message, $attachment] as $model) {
        // Position 14 of the canonical string form is the version nibble.
        expect($model->getKey()[14])->toBe('7');
    }
});

it('emits keys that are valid canonical UUIDs', function () {
    $ticket = Ticket::factory()->create();

    expect((string) Uuid::fromString((string) $ticket->getKey()))
        ->toBe((string) $ticket->getKey());
});
