<?php

declare(strict_types=1);

use AichaDigital\Laratickets\Notifications\Recipient;

describe('Recipient::user', function () {
    it('builds a user recipient that carries the user id', function () {
        $recipient = Recipient::user('0194a000-0000-7000-8000-000000000001');

        expect($recipient)
            ->isUser()->toBeTrue()
            ->isMailbox()->toBeFalse()
            ->userId->toBe('0194a000-0000-7000-8000-000000000001')
            ->email->toBeNull();
    });
});

describe('Recipient::mailbox', function () {
    it('builds a mailbox recipient that carries the email', function () {
        $recipient = Recipient::mailbox('support@example.test');

        expect($recipient)
            ->isMailbox()->toBeTrue()
            ->isUser()->toBeFalse()
            ->email->toBe('support@example.test')
            ->userId->toBeNull();
    });
});
