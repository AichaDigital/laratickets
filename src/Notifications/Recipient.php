<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Notifications;

/**
 * A notification destination resolved by the Core for a routable ticket event.
 *
 * A Recipient is a tagged union: either a user reference (carrying the
 * consumer's user id) or a mailbox (carrying a plain email address). The Core
 * never loads the consumer's User model — that is the consumer's job, which
 * keeps the package agnostic to the user model and lets the test environment
 * run without a `users` table.
 */
final readonly class Recipient
{
    private function __construct(
        public ?string $userId,
        public ?string $email,
    ) {}

    public static function user(string $userId): self
    {
        return new self($userId, null);
    }

    public static function mailbox(string $email): self
    {
        return new self(null, $email);
    }

    public function isUser(): bool
    {
        return $this->userId !== null;
    }

    public function isMailbox(): bool
    {
        return $this->email !== null;
    }
}
