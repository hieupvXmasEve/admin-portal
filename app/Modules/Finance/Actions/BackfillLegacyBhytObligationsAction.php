<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Support\Facades\DB;

/**
 * Backfill legacy bhyt finance_charges into FinanceObligation rows.
 *
 * Does not create charges. Links existing active/voided BHYT charges to
 * obligations with legacy_backfill provenance. Idempotent on re-run.
 */
class BackfillLegacyBhytObligationsAction
{
    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
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
                ->where('charge_type', FinanceCharge::TYPE_BHYT)
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

        $sourceRef = FinanceOwnedObligationSource::legacyBhytChargeRef((int) $charge->id);
        $obligation = $this->findObligation($sourceRef);

        if ($obligation instanceof FinanceObligation) {
            $this->linkExistingObligation($charge, $obligation, $summary, $dryRun);

            return;
        }

        // Already linked to a different obligation (e.g. post-cutover intake path).
        if ($charge->finance_obligation_id !== null) {
            $summary['already_linked']++;

            return;
        }

        if ($dryRun) {
            $summary['created']++;

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $obligation = FinanceObligation::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => FinanceOwnedObligationSource::NON_ACADEMIC_BATCH,
            'source_ref' => $sourceRef,
            'obligation_type' => FinanceCharge::TYPE_BHYT,
            'lifecycle_status' => $charge->status === FinanceCharge::STATUS_VOID
                ? FinanceObligation::STATUS_VOIDED
                : FinanceObligation::STATUS_ACCEPTED,
            'amount' => $charge->amount,
            'currency' => 'VND',
            'pricing_rule_version' => 'bhyt:legacy_backfill',
            'pricing_snapshot' => $this->pricingSnapshot($charge),
            'accepted_at' => $charge->status === FinanceCharge::STATUS_ACTIVE
                ? ($charge->created_at ?? now())
                : null,
        ]);

        $charge->update(['finance_obligation_id' => $obligation->id]);
        $summary['created']++;
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
    }

    private function ensureObligationBillingAccount(FinanceObligation $obligation, FinanceCharge $charge): void
    {
        if ($obligation->billing_account_id !== null || $charge->student_id === null) {
            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);
        $obligation->update(['billing_account_id' => $billingAccount->id]);
    }

    private function findObligation(string $sourceRef): ?FinanceObligation
    {
        return FinanceObligation::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', FinanceOwnedObligationSource::NON_ACADEMIC_BATCH)
            ->where('source_ref', $sourceRef)
            ->where('obligation_type', FinanceCharge::TYPE_BHYT)
            ->lockForUpdate()
            ->first();
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
    private function detail(FinanceCharge $charge, string $status, string $reason): array
    {
        return [
            'status' => $status,
            'reason' => $reason,
            'charge_id' => (int) $charge->id,
            'charge_type' => $charge->charge_type,
            'charge_status' => $charge->status,
            'charge_amount' => (float) $charge->amount,
            'source_type' => $charge->source_type,
            'source_id' => $charge->source_id === null ? null : (int) $charge->source_id,
            'source_kind' => FinanceOwnedObligationSource::NON_ACADEMIC_BATCH,
            'source_ref' => FinanceOwnedObligationSource::legacyBhytChargeRef((int) $charge->id),
        ];
    }
}
