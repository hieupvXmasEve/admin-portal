<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use Illuminate\Support\Facades\DB;

/**
 * Backfill legacy egc_level_fee finance_charges into FinanceObligation rows.
 *
 * Does not create charges. Links existing active/voided egc_level_fee charges to
 * obligations with legacy_backfill provenance. Idempotent on re-run.
 *
 * Hard-gate: for each linked active charge that has invoice materialization,
 * derived settlement outstanding must match the old charge balance formula.
 * Mismatches are reported; the artisan command exits non-zero when any remain.
 */
class BackfillLegacyEgcLevelFeeObligationsAction
{
    private const AMOUNT_TOLERANCE = 0.009;

    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
        private readonly ObligationSettlementReader $settlementReader,
    ) {}

    /**
     * @return array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }
     */
    public function run(bool $dryRun = false): array
    {
        return DB::transaction(function () use ($dryRun): array {
            $summary = [
                'checked' => 0,
                'created' => 0,
                'already_linked' => 0,
                'linked_existing' => 0,
                'skipped' => 0,
                'mismatches' => 0,
                'details' => [],
            ];

            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                ->whereIn('status', [
                    FinanceCharge::STATUS_ACTIVE,
                    FinanceCharge::STATUS_VOID,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(200, function ($charges) use (&$summary, $dryRun): void {
                    foreach ($charges as $charge) {
                        $summary['checked']++;
                        $this->backfillCharge($charge, $summary, $dryRun);
                    }
                });

            return $summary;
        });
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function backfillCharge(FinanceCharge $charge, array &$summary, bool $dryRun): void
    {
        if ($charge->student_id === null) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'missing_student_id');

            return;
        }

        $sourceRef = FinanceOwnedObligationSource::legacyEgcLevelFeeChargeRef((int) $charge->id);
        $legacyObligation = $this->findLegacyObligation($sourceRef);

        if ($legacyObligation instanceof FinanceObligation) {
            $this->linkExistingObligation($charge, $legacyObligation, $summary, $dryRun);

            return;
        }

        // Already linked via post-cutover intake (different source_ref/kind).
        if ($charge->finance_obligation_id !== null) {
            $summary['already_linked']++;
            $linked = FinanceObligation::query()->find($charge->finance_obligation_id);

            if ($linked instanceof FinanceObligation) {
                if (! $dryRun) {
                    $this->ensureObligationBillingAccount($linked, $charge);
                }
                $this->reconcileSettlement($charge, $linked, $summary);
            }

            return;
        }

        if ($dryRun) {
            $summary['created']++;
            $this->reconcileSettlementPreview($charge, $summary);

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $obligation = FinanceObligation::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_LEGACY_EGC,
            'source_ref' => $sourceRef,
            'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'lifecycle_status' => $charge->status === FinanceCharge::STATUS_VOID
                ? FinanceObligation::STATUS_VOIDED
                : FinanceObligation::STATUS_ACCEPTED,
            'amount' => $charge->amount,
            'currency' => 'VND',
            'pricing_rule_version' => 'egc_level_fee:legacy_backfill',
            'pricing_snapshot' => $this->pricingSnapshot($charge),
            'accepted_at' => $charge->status === FinanceCharge::STATUS_ACTIVE
                ? ($charge->created_at ?? now())
                : null,
        ]);

        $charge->update(['finance_obligation_id' => $obligation->id]);
        $summary['created']++;
        $this->reconcileSettlement($charge->fresh() ?? $charge, $obligation, $summary);
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function linkExistingObligation(
        FinanceCharge $charge,
        FinanceObligation $obligation,
        array &$summary,
        bool $dryRun,
    ): void {
        if ((int) $charge->finance_obligation_id === (int) $obligation->id) {
            if (! $dryRun) {
                $this->ensureObligationBillingAccount($obligation, $charge);
            }
            $summary['already_linked']++;
            $this->reconcileSettlement($charge, $obligation, $summary);

            return;
        }

        $linkedCharge = $obligation->financeCharge()->first();

        if (
            $charge->finance_obligation_id !== null
            || ($linkedCharge instanceof FinanceCharge && (int) $linkedCharge->id !== (int) $charge->id)
        ) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'obligation_link_conflict');

            return;
        }

        if (! $dryRun) {
            $charge->update(['finance_obligation_id' => $obligation->id]);
            $this->ensureObligationBillingAccount($obligation, $charge);
        }

        $summary['linked_existing']++;
        $this->reconcileSettlement($charge->fresh() ?? $charge, $obligation, $summary);
    }

    private function ensureObligationBillingAccount(FinanceObligation $obligation, FinanceCharge $charge): void
    {
        if ($obligation->billing_account_id !== null || $charge->student_id === null) {
            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);
        $obligation->update(['billing_account_id' => $billingAccount->id]);
    }

    private function findLegacyObligation(string $sourceRef): ?FinanceObligation
    {
        return FinanceObligation::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', SubmitEgcLevelFeeDebitAction::SOURCE_KIND_LEGACY_EGC)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function reconcileSettlement(
        FinanceCharge $charge,
        FinanceObligation $obligation,
        array &$summary,
    ): void {
        if ($charge->status === FinanceCharge::STATUS_VOID) {
            $summary['details'][] = $this->detail(
                $charge,
                'reconciled',
                'voided_not_collectable',
                $obligation,
                0.0,
                0.0,
            );

            return;
        }

        $oldBalance = $this->oldComputedBalance($charge);
        $hasActiveLines = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveLines) {
            if ($this->amountsDiffer((float) $obligation->amount, (float) $charge->amount)) {
                $summary['mismatches']++;
                $summary['details'][] = $this->detail(
                    $charge,
                    'mismatch',
                    'obligation_amount_mismatch',
                    $obligation,
                    $oldBalance,
                    null,
                );
            } else {
                $summary['details'][] = $this->detail(
                    $charge,
                    'reconciled_without_invoice',
                    'no_active_invoice_lines',
                    $obligation,
                    $oldBalance,
                    null,
                );
            }

            return;
        }

        $settlement = $this->settlementReader->getSettlement(
            sourceSystem: (string) $obligation->source_system,
            sourceKind: (string) $obligation->source_kind,
            sourceRef: (string) $obligation->source_ref,
            obligationType: (string) $obligation->obligation_type,
        );

        $derivedOutstanding = $settlement->outstanding;

        if ($this->amountsDiffer($oldBalance, $derivedOutstanding)) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail(
                $charge,
                'mismatch',
                'settlement_outstanding_mismatch',
                $obligation,
                $oldBalance,
                $derivedOutstanding,
            );

            return;
        }

        $summary['details'][] = $this->detail(
            $charge,
            'reconciled',
            'settlement_matches_old_balance',
            $obligation,
            $oldBalance,
            $derivedOutstanding,
        );
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function reconcileSettlementPreview(FinanceCharge $charge, array &$summary): void
    {
        if ($charge->status === FinanceCharge::STATUS_VOID) {
            return;
        }

        $oldBalance = $this->oldComputedBalance($charge);
        $hasActiveLines = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveLines) {
            $summary['details'][] = $this->detail(
                $charge,
                'reconciled_without_invoice',
                'no_active_invoice_lines',
                null,
                $oldBalance,
                null,
            );

            return;
        }

        $linePayable = (float) InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->sum('amount_snapshot');

        $paid = $this->settlementService->getChargePaidAmount((int) $charge->id);
        $discount = $this->settlementService->getChargeDiscountAmount((int) $charge->id);
        $lineOutstanding = max(0.0, $linePayable - $discount - $paid);

        if ($this->amountsDiffer($oldBalance, $lineOutstanding)) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail(
                $charge,
                'mismatch',
                'settlement_outstanding_mismatch',
                null,
                $oldBalance,
                $lineOutstanding,
            );

            return;
        }

        $summary['details'][] = $this->detail(
            $charge,
            'reconciled',
            'settlement_matches_old_balance',
            null,
            $oldBalance,
            $lineOutstanding,
        );
    }

    private function oldComputedBalance(FinanceCharge $charge): float
    {
        if ((float) $charge->amount <= 0) {
            return 0.0;
        }

        $paid = $this->settlementService->getChargePaidAmount((int) $charge->id);
        $discount = $this->settlementService->getChargeDiscountAmount((int) $charge->id);

        return max(0.0, (float) $charge->amount - $paid - $discount);
    }

    /**
     * @return array<string, mixed>
     */
    private function pricingSnapshot(FinanceCharge $charge): array
    {
        return [
            'provenance' => 'legacy_backfill',
            'legacy_charge_id' => (int) $charge->id,
            'legacy_charge_status' => $charge->status,
            'legacy_charge_amount' => (float) $charge->amount,
            'legacy_source_type' => $charge->source_type,
            'legacy_source_id' => $charge->source_id === null ? null : (int) $charge->source_id,
            'legacy_semester_id' => $charge->semester_id === null ? null : (int) $charge->semester_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(
        FinanceCharge $charge,
        string $status,
        string $reason,
        ?FinanceObligation $obligation = null,
        ?float $oldBalance = null,
        ?float $derivedOutstanding = null,
    ): array {
        return [
            'status' => $status,
            'reason' => $reason,
            'charge_id' => (int) $charge->id,
            'charge_type' => $charge->charge_type,
            'charge_status' => $charge->status,
            'charge_amount' => (float) $charge->amount,
            'source_type' => $charge->source_type,
            'source_id' => $charge->source_id === null ? null : (int) $charge->source_id,
            'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_LEGACY_EGC,
            'source_ref' => FinanceOwnedObligationSource::legacyEgcLevelFeeChargeRef((int) $charge->id),
            'obligation_id' => $obligation?->id,
            'old_balance' => $oldBalance,
            'derived_outstanding' => $derivedOutstanding,
        ];
    }

    private function amountsDiffer(float $left, float $right): bool
    {
        return abs(round($left, 2) - round($right, 2)) > self::AMOUNT_TOLERANCE;
    }
}
