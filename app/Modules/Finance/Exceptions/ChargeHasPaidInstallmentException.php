<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/**
 * Thrown when admin tries to (re-)split a charge that already has at least one
 * installment in `paid` status. Phase 1 lock per spec D2: plan becomes readonly
 * after first paid installment. Phase 2 may relax this with partial edits.
 */
class ChargeHasPaidInstallmentException extends RuntimeException
{
    public function __construct(int $chargeId)
    {
        parent::__construct(
            "FinanceCharge #{$chargeId} has at least one paid installment. ".
            'Plan is locked (Phase 1 invariant).'
        );
    }
}
