<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Exceptions;

use RuntimeException;

/**
 * Internal signal thrown by DngReservationLifecycle::reserve() when a live
 * collection is eligible for automatic cancel-then-push replacement.
 *
 * The provider cancel must never run while a DB transaction/lock for this
 * billing account is open, so the eligibility decision (made inside the
 * reservation transaction) cannot perform the cancel itself. This exception
 * unwinds that transaction; whichever caller owns the outermost
 * SettlementMutationGuard scope for the billing account catches it, performs
 * the cancel outside any transaction, and retries the reservation.
 */
final class DngReservationReplacementRequired extends RuntimeException
{
    public function __construct(public readonly int $existingRequestId)
    {
        parent::__construct("DNG payment request #{$existingRequestId} is eligible for automatic cancel-then-push replacement.");
    }
}
