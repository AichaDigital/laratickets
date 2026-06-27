<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use AichaDigital\Laratickets\Events\TicketMessagePosted;
use AichaDigital\Laratickets\Exceptions\TicketAuthorizationException;
use AichaDigital\Laratickets\Exceptions\TicketMessageRejected;
use AichaDigital\Laratickets\Exceptions\TicketStateException;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketMessage;
use AichaDigital\Laratickets\Support\ActorId;
use Illuminate\Database\Eloquent\Collection;

class TicketMessageService
{
    private const REDACTED_PLACEHOLDER = '[redacted]';

    public function __construct(
        protected TicketAuthorizationContract $authorization,
    ) {}

    /**
     * @param  mixed  $by
     */
    public function post(
        Ticket $ticket,
        $by,
        string $body,
        MessageAuthorRole $role,
    ): TicketMessage {
        if (! config('laratickets.messages.enabled', true)) {
            throw new TicketStateException('Ticket messages are disabled.');
        }

        $trimmedBody = trim($body);
        if ($trimmedBody === '') {
            throw TicketMessageRejected::empty();
        }

        $maxLength = (int) config('laratickets.messages.max_body_length', 5000);
        if ($maxLength > 0 && mb_strlen($trimmedBody) > $maxLength) {
            throw TicketMessageRejected::tooLong($maxLength);
        }

        if (! $this->authorization->canPostMessage($by, $ticket, $role)) {
            throw new TicketAuthorizationException('User is not authorized to post message on this ticket.');
        }

        $message = new TicketMessage([
            'ticket_id' => $ticket->id,
            'author_id' => ActorId::of($by),
            'author_role' => $role,
            'visibility' => MessageVisibility::PUBLIC,
            'body' => $trimmedBody,
        ]);
        $message->save();

        event(new TicketMessagePosted($message));

        return $message;
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    /**
     * @param  mixed  $viewer
     * @return Collection<int, TicketMessage>
     */
    public function listFor(Ticket $ticket, $viewer): Collection
    {
        $query = $ticket->messages();

        if (! $this->authorization->canViewInternalMessages($viewer, $ticket)) {
            $query->where('visibility', MessageVisibility::PUBLIC->value);
        }

        return $query->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * @param  mixed  $by
     */
    public function redact(TicketMessage $message, $by, string $reason): TicketMessage
    {
        if (! $this->authorization->canRedactMessage($by, $message)) {
            throw new TicketAuthorizationException('User is not authorized to redact this message.');
        }

        if ($message->isRedacted()) {
            return $message;
        }

        $message->body = self::REDACTED_PLACEHOLDER;
        $message->redacted_at = now();
        $message->redacted_by = ActorId::of($by);
        $message->redaction_reason = $reason;
        $message->save();

        return $message->fresh();
    }
}
