<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Exceptions;

use RuntimeException;

/**
 * Thrown when a redemption order transition is attempted from a status that
 * does not allow it (e.g. approving an order that is already collected, or a
 * second concurrent "reject" losing the race after the first already moved
 * the order out of pending_review). Callers map this to HTTP 409.
 */
class RedemptionStateConflictException extends RuntimeException {}
