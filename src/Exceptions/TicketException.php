<?php

declare(strict_types=1);

namespace AichaDigital\Laratickets\Exceptions;

use RuntimeException;

/**
 * Base type for all domain-level ticket failures.
 *
 * Extends \RuntimeException so existing `catch (\RuntimeException)` call sites
 * in consumer apps keep working across the v1.0 boundary. New code may catch the
 * specific subtype for control flow (e.g. 403 vs 409/422) instead of
 * string-matching on the message.
 */
abstract class TicketException extends RuntimeException {}
