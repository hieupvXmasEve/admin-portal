<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationChargeState;
use App\Shared\Contracts\Finance\FinanceCancellationChargeStateReader;

/**
 * Resolves paid/unpaid cancellation preconditions without exposing DNG/charge
 * models to source contexts.
 */
class ResolveFinanceCancellationChargeStateAction implements FinanceCancellationChargeStateReader
{
    public function __construct(
        private readonly BridgePaidDngRequestsForChargeAction $bridgePaidDngRequestsForChargeAction,
    ) {}

    public function forCharge(?int $financeChargeId): FinanceCancellationChargeState
    {
        if ($financeChargeId === null) {
            return new FinanceCancellationChargeState(
                financeChargeId: null,
                hasActiveCharge: false,
                isPaid: false,
                hasUnpaidActiveCharge: false,
                requiresNoRefundAcknowledgement: false,
                requiresUnpaidVoidConfirmation: false,
            );
        }

        $charge = FinanceCharge::query()->find($financeChargeId);
        if (! $charge instanceof FinanceCharge || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return new FinanceCancellationChargeState(
                financeChargeId: $financeChargeId,
                hasActiveCharge: false,
                isPaid: false,
                hasUnpaidActiveCharge: false,
                requiresNoRefundAcknowledgement: false,
                requiresUnpaidVoidConfirmation: false,
            );
        }

        $hasPaidDng = $this->bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($charge);
        $isPaid = $charge->is_fully_paid || $hasPaidDng;
        $hasUnpaidActiveCharge = ! $isPaid;

        return new FinanceCancellationChargeState(
            financeChargeId: (int) $charge->id,
            hasActiveCharge: true,
            isPaid: $isPaid,
            hasUnpaidActiveCharge: $hasUnpaidActiveCharge,
            requiresNoRefundAcknowledgement: $isPaid,
            requiresUnpaidVoidConfirmation: $hasUnpaidActiveCharge,
        );
    }

    /** @param array{finance_charge_id?: int|null} $data */
    public static function run(array $data): FinanceCancellationChargeState
    {
        return app(self::class)->forCharge(
            array_key_exists('finance_charge_id', $data) ? ($data['finance_charge_id'] === null ? null : (int) $data['finance_charge_id']) : null
        );
    }
}
