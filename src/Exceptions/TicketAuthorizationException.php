<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

/**
 * The actor is not allowed to perform the requested ticket operation.
 *
 * Consumers typically map this to HTTP 403.
 */
final class TicketAuthorizationException extends TicketException {}
