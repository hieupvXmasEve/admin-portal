<?php

declare(strict_types=1);

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the last installment of a charge transitions to `paid` (no
 * pending installments left).
 *
 * Future listeners:
 * - Invoice receipt generator (final receipt).
 * - Student notification ("Học phí đã thanh toán đủ").
 * - Audit log close-out entry.
 *
 * Email/invoice wiring deferred to follow-up story (see harness backlog).
 */
class ChargeFullySettled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FinanceCharge $charge,
    ) {}
}
