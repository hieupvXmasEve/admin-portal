<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill 1 installment per existing ACTIVE charge with positive amount,
     * so downstream code paths never branch on legacy charges. Credit charges
     * (amount <= 0) are skipped — they are settled via invoice allocation,
     * not via student payment, and never push to DNG.
     *
     * Installment amount = net split target = charge.amount - discount_amount
     * (matches the existing DngPaymentService behavior which already uses
     * $charges->sum('balance') for DNG push — see CreateBatchDngFromChargesAction).
     *
     * Status is derived from the latest dng_payment_request linked to the charge.
     *
     * Guard: fails if finance_charge_installments already has rows — prevents
     * accidental double-backfill across environments.
     */
    public function up(): void
    {
        if (DB::table('finance_charge_installments')->exists()) {
            throw new RuntimeException(
                'finance_charge_installments already contains rows. '.
                'Refusing to run backfill. Run pre-backfill cleanup if intentional.'
            );
        }

        $fallbackDueDate = Carbon::today()->addDays(30);
        $now = now();

        FinanceCharge::query()
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->with('billingCycle:id,due_date')
            ->orderBy('id')
            ->chunkById(500, function ($charges) use ($fallbackDueDate, $now): void {
                $chargeIds = $charges->pluck('id');

                $latestDngByCharge = DB::table('dng_payment_requests')
                    ->whereIn('finance_charge_id', $chargeIds)
                    ->select(['finance_charge_id', 'id', 'status', 'paid_at', 'amount'])
                    ->orderBy('finance_charge_id')
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('finance_charge_id')
                    ->map(fn ($group) => $group->first());

                $rows = [];

                foreach ($charges as $charge) {
                    // Net split target: gross minus credits applied to this charge's invoice lines.
                    // Matches DngPaymentService push amount (uses $charge->balance).
                    $netTarget = (float) $charge->amount - (float) $charge->discount_amount;

                    if ($netTarget <= 0) {
                        // Fully covered by credits → no installment needed.
                        continue;
                    }

                    $dueDate = $charge->billingCycle?->due_date ?? $fallbackDueDate;
                    $latestDng = $latestDngByCharge[$charge->id] ?? null;

                    [$status, $paidAt, $dngId, $installmentAmount] = $this->mapStatus($latestDng, $netTarget);

                    $rows[] = [
                        'finance_charge_id' => $charge->id,
                        'installment_no' => 1,
                        'amount' => $installmentAmount,
                        'due_date' => $dueDate instanceof Carbon ? $dueDate->toDateString() : (string) $dueDate,
                        'status' => $status,
                        'dng_payment_request_id' => $dngId,
                        'paid_at' => $paidAt,
                        'push_attempt_count' => 0,
                        'last_push_error' => null,
                        'last_push_attempted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('finance_charge_installments')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // Intentional no-op: backfill data is part of the production state once
        // run. Use a manual SQL script if rollback is truly required.
    }

    /**
     * Map current DNG state to installment status + amount.
     *
     * For status=awaiting/paid → prefer DNG.amount (preserves history of what was actually pushed/paid).
     * For status=pending → use computed netTarget (fresh state, no DNG yet).
     *
     * @return array{0:string, 1:?string, 2:?int, 3:float} [status, paid_at, dng_payment_request_id, amount]
     */
    private function mapStatus(?object $dng, float $netTarget): array
    {
        if ($dng === null) {
            return [FinanceChargeInstallment::STATUS_PENDING, null, null, $netTarget];
        }

        $status = strtolower((string) $dng->status);

        if ($status === 'paid') {
            return [FinanceChargeInstallment::STATUS_PAID, $dng->paid_at, $dng->id, (float) $dng->amount];
        }

        if (in_array($status, ['awaiting_payment', 'pending', 'pushed'], true)) {
            return [FinanceChargeInstallment::STATUS_AWAITING_PAYMENT, null, $dng->id, (float) $dng->amount];
        }

        // cancelled / failed / unknown → treat as pending so admin can retry; use netTarget.
        return [FinanceChargeInstallment::STATUS_PENDING, null, null, $netTarget];
    }
};
