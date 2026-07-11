<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\Enums;

/**
 * Shared fee-disposition outcomes for Finance Cancellation Operations.
 * Source contexts map these into their own UI/state fields; they must not
 * import Finance action constants.
 */
enum FinanceCancellationFeeDisposition: string
{
    case NoCharge = 'no_charge';
    case VoidedUnpaidCharge = 'voided_unpaid_charge';
    case KeptPaidNoRefund = 'kept_paid_no_refund';
}
