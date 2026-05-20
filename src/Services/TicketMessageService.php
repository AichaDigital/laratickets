<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Services;

use AichaDigital\Laratickets\Contracts\TicketAuthorizationContract;
use AichaDigital\Laratickets\Enums\MessageAuthorRole;
use AichaDigital\Laratickets\Enums\MessageVisibility;
use AichaDigital\Laratickets\Events\TicketMessagePosted;
use AichaDigital\Laratickets\Models\Ticket;
use AichaDigital\Laratickets\Models\TicketMessage;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class TicketMessageService
{
    private const REDACTED_PLACEHOLDER = '[redacted]';

    public function __construct(
        protected TicketAuthorizationContract $authorization,
    ) {}

    /**
     * @param  mixed  $author
     */
    public function post(
        Ticket $ticket,
        $author,
        string $body,
        MessageAuthorRole $role,
    ): TicketMessage {
        if (! config('laratickets.messages.enabled', true)) {
            throw new RuntimeException('Ticket messages are disabled.');
        }

        $trimmedBody = trim($body);
        if ($trimmedBody === '') {
            throw new RuntimeException('Message body cannot be empty.');
        }

        $maxLength = (int) config('laratickets.messages.max_body_length', 5000);
        if ($maxLength > 0 && mb_strlen($trimmedBody) > $maxLength) {
            throw new RuntimeException("Message body exceeds max length ($maxLength chars). ");
        }

        if (! $this->authorization->canPostMessage($author, $ticket, $role)) {
            throw new RuntimeException('User is not authorized to post message on this ticket.');
        }

        $message = new TicketMessage([
            'ticket_id' => $ticket->id,
            'author_id' => $author->{config('laratickets.user.id_column', 'id')},
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
     * @param  mixed  $redactor
     */
    public function redact(TicketMessage $message, $redactor, string $reason): TicketMessage
    {
        if (! $this->authorization->canRedactMessage($redactor, $message)) {
            throw new RuntimeException('User is not authorized to redact this message.');
        }

        if ($message->isRedacted()) {
            return $message;
        }

        $message->body = self::REDACTED_PLACEHOLDER;
        $message->redacted_at = now();
        $message->redacted_by = $redactor->{config('laratickets.user.id_column', 'id')};
        $message->redaction_reason = $reason;
        $message->save();

        return $message->fresh();
    }
}
