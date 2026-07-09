<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Convert legacy defer_credit negative charge rows into FinanceCreditEntitlement
 * + credit applications (ADR-0030). Voids the negative line in the same
 * transaction. Hard-gates on unchanged remaining settlement.
 */
class BackfillLegacyDeferCreditEntitlementsAction
{
    private const RECONCILE_TOLERANCE = 0.01;

    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
        private readonly VoidFinanceChargeAction $voidFinanceChargeAction,
    ) {}

    /**
     * @return array{
     *     checked:int,
     *     converted:int,
     *     already_converted:int,
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
                'converted' => 0,
                'already_converted' => 0,
                'skipped' => 0,
                'mismatches' => 0,
                'details' => [],
            ];

            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_DEFER_CREDIT)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('amount', '<', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(100, function ($charges) use (&$summary, $dryRun): void {
                    foreach ($charges as $charge) {
                        $summary['checked']++;
                        $this->convertCharge($charge, $summary, $dryRun);
                    }
                });

            return $summary;
        });
    }

    /**
     * @param  array{
     *     checked:int,
     *     converted:int,
     *     already_converted:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function convertCharge(FinanceCharge $charge, array &$summary, bool $dryRun): void
    {
        if ($charge->student_id === null) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'missing_student_id');

            return;
        }

        $sourceRef = FinanceOwnedObligationSource::legacyDeferCreditChargeRef((int) $charge->id);
        $existing = FinanceCreditEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', 'legacy_defer_credit')
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_DEFER_CREDIT)
            ->first();

        if ($existing instanceof FinanceCreditEntitlement) {
            $summary['already_converted']++;
            $summary['details'][] = $this->detail($charge, 'already_converted', 'entitlement_exists', [
                'entitlement_id' => $existing->id,
            ]);

            return;
        }

        $creditAmount = abs((float) $charge->amount);
        if ($creditAmount <= 0) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'non_positive_amount');

            return;
        }

        $negativeLines = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->get();

        $invoiceIds = $negativeLines
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $preSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $targetLines = $this->targetPositiveLinesForConversion($charge, $invoiceIds);

        if ($dryRun) {
            $summary['converted']++;
            $summary['details'][] = $this->detail($charge, 'would_convert', 'dry_run', [
                'credit_amount' => $creditAmount,
                'invoice_ids' => $invoiceIds,
                'target_line_count' => $targetLines->count(),
                'pre_remaining' => collect($preSnapshots)->map(fn (array $s) => $s['remaining'])->all(),
            ]);

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $entitlement = FinanceCreditEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => 'legacy_defer_credit',
            'source_ref' => $sourceRef,
            'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT,
            'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceCreditEntitlement::ALLOCATION_AVAILABLE,
            'amount' => $creditAmount,
            'currency' => 'VND',
            'pricing_rule_version' => 'defer_credit:legacy_backfill',
            'pricing_snapshot' => [
                'pricing_strategy' => 'legacy_backfill',
                'legacy_charge_id' => $charge->id,
                'legacy_amount' => (float) $charge->amount,
                'description' => $charge->description,
            ],
            'approved_at' => now(),
        ]);

        // Void the negative line first so settlement never double-counts the
        // legacy netting fallback together with the new credit applications.
        $this->voidFinanceChargeAction->handle(
            chargeId: (int) $charge->id,
            reason: 'Converted to FinanceCreditEntitlement (legacy defer_credit backfill)',
            userId: null,
            autoReallocate: false,
        );

        $remaining = $creditAmount;
        $applicationIds = [];

        foreach ($targetLines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $line->refresh();
            $capacity = $this->settlementService->getLineOutstandingAmount($line);
            if ($capacity <= 0) {
                continue;
            }

            $applyAmount = min($remaining, $capacity);
            $application = CreditApplication::query()->create([
                'finance_credit_entitlement_id' => $entitlement->id,
                'invoice_line_id' => $line->id,
                'amount' => $applyAmount,
                'entry_type' => CreditApplication::ENTRY_APPLICATION,
                'source_ref_type' => self::class,
                'source_ref_id' => $charge->id,
                'applied_at' => now(),
                'created_by' => null,
            ]);

            $applicationIds[] = (int) $application->id;
            $remaining -= $applyAmount;

            $invoice = $line->invoice()->first();
            if ($invoice instanceof StudentInvoice) {
                $this->settlementService->recalculateInvoiceSnapshot($invoice);
            }
        }

        $applied = $creditAmount - $remaining;
        $allocationStatus = FinanceCreditEntitlement::ALLOCATION_AVAILABLE;
        if ($applied >= $creditAmount && $creditAmount > 0) {
            $allocationStatus = FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED;
        } elseif ($applied > 0) {
            $allocationStatus = FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED;
        }

        $entitlement->forceFill(['allocation_status' => $allocationStatus])->save();

        $postSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $mismatch = $this->reconcileSnapshots($preSnapshots, $postSnapshots);

        if ($mismatch !== null) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($charge, 'mismatch', $mismatch, [
                'entitlement_id' => $entitlement->id,
                'application_ids' => $applicationIds,
                'pre' => $preSnapshots,
                'post' => $postSnapshots,
            ]);

            // Hard gate: abort so the outer transaction rolls back all conversions
            // in this run (no drifted settlement may stick).
            throw new RuntimeException(
                "Defer credit conversion reconciliation failed for charge #{$charge->id}: {$mismatch}"
            );
        }

        $summary['converted']++;
        $summary['details'][] = $this->detail($charge, 'converted', 'ok', [
            'entitlement_id' => $entitlement->id,
            'application_ids' => $applicationIds,
            'applied_amount' => $applied,
        ]);
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return Collection<int, InvoiceLine>
     */
    private function targetPositiveLinesForConversion(FinanceCharge $charge, array $invoiceIds): Collection
    {
        $query = InvoiceLine::query()
            ->with(['invoice', 'charge'])
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->whereHas('charge', function ($q): void {
                $q->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->where('amount', '>', 0);
            })
            ->orderBy('created_at')
            ->orderBy('id');

        if ($invoiceIds !== []) {
            $query->whereIn('invoice_id', $invoiceIds);
        } else {
            $query->whereHas('invoice', function ($invoiceQuery) use ($charge): void {
                $invoiceQuery->where('student_id', $charge->student_id);
                if ($charge->semester_id !== null) {
                    $invoiceQuery->where('semester_id', $charge->semester_id);
                }
            });
        }

        return $query->get();
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array<int, array{net:float,remaining:float,paid:float,discount:float,credit:float}>
     */
    private function captureInvoiceSnapshots(array $invoiceIds): array
    {
        $snapshots = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = StudentInvoice::query()->find($invoiceId);
            if (! $invoice instanceof StudentInvoice) {
                continue;
            }

            $snapshot = $this->settlementService->deriveInvoiceSnapshot($invoice);
            $snapshots[(int) $invoiceId] = [
                'net' => (float) $snapshot['net'],
                'remaining' => (float) $snapshot['remaining'],
                'paid' => (float) $snapshot['paid'],
                'discount' => (float) $snapshot['discount'],
                'credit' => (float) ($snapshot['credit'] ?? 0),
            ];
        }

        return $snapshots;
    }

    /**
     * @param  array<int, array{net:float,remaining:float,paid:float,discount:float,credit:float}>  $pre
     * @param  array<int, array{net:float,remaining:float,paid:float,discount:float,credit:float}>  $post
     */
    private function reconcileSnapshots(array $pre, array $post): ?string
    {
        foreach ($pre as $invoiceId => $before) {
            $after = $post[$invoiceId] ?? null;
            if ($after === null) {
                return "missing_post_snapshot_invoice_{$invoiceId}";
            }

            // Remaining (what the student still owes) is the hard settlement
            // invariant. Net may shift when the negative-line discount fallback
            // is replaced by credit applications.
            if (abs($before['remaining'] - $after['remaining']) > self::RECONCILE_TOLERANCE) {
                return "remaining_drift_invoice_{$invoiceId}: {$before['remaining']} → {$after['remaining']}";
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function detail(FinanceCharge $charge, string $status, string $reason, array $extra = []): array
    {
        return array_merge([
            'charge_id' => $charge->id,
            'charge_type' => $charge->charge_type,
            'charge_amount' => (float) $charge->amount,
            'status' => $status,
            'reason' => $reason,
            'source_kind' => 'legacy_defer_credit',
            'source_ref' => FinanceOwnedObligationSource::legacyDeferCreditChargeRef((int) $charge->id),
        ], $extra);
    }
}
