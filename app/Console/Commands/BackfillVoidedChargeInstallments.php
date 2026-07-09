<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Console\Command;

/**
 * FIN-12 remediation: normalize installments left live on already-voided charges.
 *
 * A voided charge must not keep collectible installments. This backfill cancels:
 * - pending / awaiting_payment installments on voided charges, and
 * - paid installments whose charge's payments net to zero (fully reversed).
 *
 * Run this once before the AuditFinanceInvariants live-installment check is
 * expected to read zero. Idempotent and safe to re-run; supports --dry-run.
 */
class BackfillVoidedChargeInstallments extends Command
{
    protected $signature = 'finance:backfill-voided-charge-installments
        {--dry-run : Report what would change without writing}
        {--chunk=200 : Voided charges processed per chunk}';

    protected $description = 'Cancel installments left live on voided finance charges (FIN-12)';

    public function handle(SettlementService $settlementService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $cancelledTotal = 0;
        $chargesTouched = 0;

        FinanceCharge::query()
            ->where('status', FinanceCharge::STATUS_VOID)
            ->whereHas('installments', fn ($q) => $q->whereIn('status', [
                FinanceChargeInstallment::STATUS_PENDING,
                FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                FinanceChargeInstallment::STATUS_PAID,
            ]))
            ->orderBy('id')
            ->chunkById($chunkSize, function ($charges) use ($settlementService, $dryRun, &$cancelledTotal, &$chargesTouched) {
                foreach ($charges as $charge) {
                    $cancelled = $this->normalizeCharge($charge, $settlementService, $dryRun);

                    if ($cancelled > 0) {
                        $cancelledTotal += $cancelled;
                        $chargesTouched++;
                    }
                }
            });

        $verb = $dryRun ? 'Would cancel' : 'Cancelled';
        $this->info("{$verb} {$cancelledTotal} installment(s) across {$chargesTouched} voided charge(s).");

        return self::SUCCESS;
    }

    private function normalizeCharge(FinanceCharge $charge, SettlementService $settlementService, bool $dryRun): int
    {
        $installments = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->get();

        $chargeNetPaid = $settlementService->getChargePaidAmount($charge->id);
        $cancelled = 0;

        foreach ($installments as $installment) {
            $isNonSettled = in_array($installment->status, [
                FinanceChargeInstallment::STATUS_PENDING,
                FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
            ], true);

            $isReversedPaid = $installment->status === FinanceChargeInstallment::STATUS_PAID
                && $chargeNetPaid <= 0.0;

            if (! $isNonSettled && ! $isReversedPaid) {
                continue;
            }

            if (! $dryRun) {
                $installment->update(['status' => FinanceChargeInstallment::STATUS_CANCELLED]);
            }

            $cancelled++;
        }

        return $cancelled;
    }
}
