<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

/**
 * A ticket message was rejected by validation.
 *
 * The rejection reason is inspectable (e.g. the configured max length) so the
 * consumer can render a precise form error instead of parsing the message text.
 */
final class TicketMessageRejected extends TicketException
{
    private function __construct(string $message, private readonly ?int $maxLength = null)
    {
        parent::__construct($message);
    }

    public static function empty(): self
    {
        return new self('Message body cannot be empty.');
    }

    public static function tooLong(int $max): self
    {
        return new self("Message body exceeds max length ($max chars).", $max);
    }

    /**
     * The configured max body length when the rejection was a length overflow;
     * null for other rejection reasons (e.g. an empty body).
     */
    public function maxLength(): ?int
    {
        return $this->maxLength;
    }
}
