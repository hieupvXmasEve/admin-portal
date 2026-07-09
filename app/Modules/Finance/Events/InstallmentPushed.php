<?php

declare(strict_types=1);

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an installment is successfully pushed to DNG and is now
 * `awaiting_payment`. Listeners should notify the student that the next
 * installment is ready to pay.
 *
 * NOTE: email/in-app template wiring is deferred to a follow-up story (see
 * harness backlog). This event is the stable integration contract; adding
 * listeners later does not require changes to PushNextInstallmentAction.
 */
class InstallmentPushed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FinanceChargeInstallment $installment,
    ) {}
}
