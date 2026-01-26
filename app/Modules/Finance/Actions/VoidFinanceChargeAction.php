<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;

class VoidFinanceChargeAction
{
    /**
     * Void an existing charge.
     */
    public function handle(int $chargeId, string $reason, ?int $userId = null): FinanceCharge
    {
        $charge = FinanceCharge::findOrFail($chargeId);

        $charge->update([
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $userId ?? auth()->id(),
            'void_reason' => $reason,
        ]);

        return $charge->fresh();
    }
}
