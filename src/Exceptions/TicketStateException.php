<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

/**
 * The requested operation is invalid for the ticket's current state, or the
 * targeted feature is disabled.
 *
 * Consumers typically map this to HTTP 409/422.
 */
final class TicketStateException extends TicketException {}
