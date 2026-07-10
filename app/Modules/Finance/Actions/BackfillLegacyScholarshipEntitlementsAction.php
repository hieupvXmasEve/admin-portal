<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Modules\Finance\Support\ScholarshipCarrierClassification;
use App\Modules\Finance\Support\ScholarshipCarrierClassifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Convert legacy scholarship_credit negative charges into the correct entitlement
 * type (ADR-0030 / wave 4).
 *
 * Classification (auditable via {@see ScholarshipCarrierClassifier}):
 *  - fee-specific (discount-allocation carrier) → FinanceDiscountEntitlement,
 *    link existing invoice_discounts, void negative line, never write credit apps
 *  - grant-like (no discount carrier) → FinanceCreditEntitlement + credit
 *    applications, void negative line in the same transaction
 *
 * A reduction must never be counted through both carriers.
 */
class BackfillLegacyScholarshipEntitlementsAction
{
    private const RECONCILE_TOLERANCE = 0.01;

    public const SOURCE_KIND = 'legacy_scholarship_credit';

    public function __construct(
        private readonly ScholarshipCarrierClassifier $classifier,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
        private readonly VoidFinanceChargeAction $voidFinanceChargeAction,
    ) {}

    /**
     * @return array{
     *     checked:int,
     *     converted:int,
     *     converted_discount:int,
     *     converted_credit:int,
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
                'converted_discount' => 0,
                'converted_credit' => 0,
                'already_converted' => 0,
                'skipped' => 0,
                'mismatches' => 0,
                'details' => [],
            ];

            FinanceCharge::query()
                ->where('charge_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
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
     *     converted_discount:int,
     *     converted_credit:int,
     *     already_converted:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function convertCharge(FinanceCharge $charge, array &$summary, bool $dryRun): void
    {
        $sourceRef = FinanceOwnedObligationSource::legacyScholarshipCreditChargeRef((int) $charge->id);
        $existing = $this->alreadyConverted($sourceRef);

        if ($existing !== null) {
            $summary['already_converted']++;
            $summary['details'][] = $this->detail($charge, 'already_converted', 'entitlement_exists', [
                'entitlement_kind' => $existing['kind'],
                'entitlement_id' => $existing['id'],
            ]);

            return;
        }

        $classification = $this->classifier->classify($charge);

        if ($classification->isSkipped()) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', $classification->reason, [
                'classification' => $classification->toArray(),
            ]);

            return;
        }

        if ($classification->isDiscount()) {
            $this->convertDiscountPath($charge, $classification, $sourceRef, $summary, $dryRun);

            return;
        }

        $this->convertCreditPath($charge, $classification, $sourceRef, $summary, $dryRun);
    }

    /**
     * @return array{kind:string,id:int}|null
     */
    private function alreadyConverted(string $sourceRef): ?array
    {
        $discount = FinanceDiscountEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', self::SOURCE_KIND)
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
            ->first();

        if ($discount instanceof FinanceDiscountEntitlement) {
            return ['kind' => 'discount', 'id' => (int) $discount->id];
        }

        $credit = FinanceCreditEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', self::SOURCE_KIND)
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
            ->first();

        if ($credit instanceof FinanceCreditEntitlement) {
            return ['kind' => 'credit', 'id' => (int) $credit->id];
        }

        return null;
    }

    /**
     * @param  array{
     *     checked:int,
     *     converted:int,
     *     converted_discount:int,
     *     converted_credit:int,
     *     already_converted:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function convertDiscountPath(
        FinanceCharge $charge,
        ScholarshipCarrierClassification $classification,
        string $sourceRef,
        array &$summary,
        bool $dryRun,
    ): void {
        $invoiceIds = $classification->invoiceIds;
        $carrierDiscounts = InvoiceDiscount::query()
            ->whereIn('id', $classification->carrierDiscountIds)
            ->get();

        if ($carrierDiscounts->isEmpty()) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'carrier_discounts_missing', [
                'classification' => $classification->toArray(),
            ]);

            return;
        }

        $preSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $preCreditApplicationCount = $this->creditApplicationsOnInvoices($invoiceIds);

        if ($dryRun) {
            $summary['converted']++;
            $summary['converted_discount']++;
            $summary['details'][] = $this->detail($charge, 'would_convert_discount', 'dry_run', [
                'classification' => $classification->toArray(),
                'pre_remaining' => collect($preSnapshots)->map(fn (array $s) => $s['remaining'])->all(),
            ]);

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $entitlement = FinanceDiscountEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => self::SOURCE_KIND,
            'source_ref' => $sourceRef,
            'entitlement_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
            'lifecycle_status' => FinanceDiscountEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceDiscountEntitlement::ALLOCATION_AVAILABLE,
            'amount' => $classification->amount,
            'currency' => 'VND',
            'pricing_rule_version' => 'scholarship_credit:legacy_backfill_discount',
            'pricing_snapshot' => [
                'pricing_strategy' => 'legacy_backfill',
                'carrier' => ScholarshipCarrierClassification::CARRIER_DISCOUNT,
                'legacy_charge_id' => $charge->id,
                'legacy_amount' => (float) $charge->amount,
                'description' => $charge->description,
                'classification_evidence' => $classification->evidence,
                'carrier_invoice_discount_ids' => $carrierDiscounts->pluck('id')->values()->all(),
            ],
            'approved_at' => now(),
        ]);

        foreach ($carrierDiscounts as $discount) {
            $discount->forceFill([
                'finance_discount_entitlement_id' => $entitlement->id,
            ])->save();
        }

        $this->voidFinanceChargeAction->handle(
            chargeId: (int) $charge->id,
            reason: 'Converted to FinanceDiscountEntitlement (legacy scholarship_credit fee-specific backfill)',
            userId: null,
            autoReallocate: false,
        );

        $this->refreshDiscountAllocationStatus($entitlement, $carrierDiscounts);

        // Invariant: fee-specific scholarship conversion must not introduce
        // credit applications for the same reduction (discount is sole carrier).
        $postCreditApplicationCount = $this->creditApplicationsOnInvoices($invoiceIds);
        if ($postCreditApplicationCount !== $preCreditApplicationCount) {
            throw new RuntimeException(
                "Scholarship discount conversion changed credit application count for charge #{$charge->id} ({$preCreditApplicationCount} → {$postCreditApplicationCount}); refusing dual-carrier representation."
            );
        }

        // Dual-carrier guard: no credit entitlement for the same source quad.
        if ($this->creditEntitlementExists($sourceRef)) {
            throw new RuntimeException(
                "Scholarship discount conversion found a credit entitlement for charge #{$charge->id}; refusing dual-carrier representation."
            );
        }

        $postSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $mismatch = $this->reconcileSnapshots($preSnapshots, $postSnapshots);

        if ($mismatch !== null) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($charge, 'mismatch', $mismatch, [
                'entitlement_id' => $entitlement->id,
                'carrier' => ScholarshipCarrierClassification::CARRIER_DISCOUNT,
                'pre' => $preSnapshots,
                'post' => $postSnapshots,
            ]);

            throw new RuntimeException(
                "Scholarship discount conversion reconciliation failed for charge #{$charge->id}: {$mismatch}"
            );
        }

        $summary['converted']++;
        $summary['converted_discount']++;
        $summary['details'][] = $this->detail($charge, 'converted_discount', 'ok', [
            'entitlement_id' => $entitlement->id,
            'carrier_discount_ids' => $carrierDiscounts->pluck('id')->all(),
            'classification' => $classification->toArray(),
        ]);
    }

    /**
     * @param  array{
     *     checked:int,
     *     converted:int,
     *     converted_discount:int,
     *     converted_credit:int,
     *     already_converted:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function convertCreditPath(
        FinanceCharge $charge,
        ScholarshipCarrierClassification $classification,
        string $sourceRef,
        array &$summary,
        bool $dryRun,
    ): void {
        $invoiceIds = $classification->invoiceIds;
        $preSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $preDiscountAllocationSum = $this->discountAllocationsOnInvoices($invoiceIds);
        $targetLines = $this->targetPositiveLinesForConversion($charge, $invoiceIds);

        if ($dryRun) {
            $summary['converted']++;
            $summary['converted_credit']++;
            $summary['details'][] = $this->detail($charge, 'would_convert_credit', 'dry_run', [
                'classification' => $classification->toArray(),
                'target_line_count' => $targetLines->count(),
                'pre_remaining' => collect($preSnapshots)->map(fn (array $s) => $s['remaining'])->all(),
            ]);

            return;
        }

        // Dual-carrier guard: never create credit apps when a discount entitlement
        // already owns this reduction (or any scholarship discount entitlement
        // was created for this source).
        if ($this->discountEntitlementExists($sourceRef)) {
            throw new RuntimeException(
                "Scholarship credit conversion found a discount entitlement for charge #{$charge->id}; refusing dual-carrier representation."
            );
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $entitlement = FinanceCreditEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => self::SOURCE_KIND,
            'source_ref' => $sourceRef,
            'entitlement_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
            'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceCreditEntitlement::ALLOCATION_AVAILABLE,
            'amount' => $classification->amount,
            'currency' => 'VND',
            'pricing_rule_version' => 'scholarship_credit:legacy_backfill_credit',
            'pricing_snapshot' => [
                'pricing_strategy' => 'legacy_backfill',
                'carrier' => ScholarshipCarrierClassification::CARRIER_CREDIT,
                'legacy_charge_id' => $charge->id,
                'legacy_amount' => (float) $charge->amount,
                'description' => $charge->description,
                'classification_evidence' => $classification->evidence,
            ],
            'approved_at' => now(),
        ]);

        // Void the negative line first so settlement never double-counts the
        // legacy netting fallback together with the new credit applications.
        $this->voidFinanceChargeAction->handle(
            chargeId: (int) $charge->id,
            reason: 'Converted to FinanceCreditEntitlement (legacy scholarship_credit grant-like backfill)',
            userId: null,
            autoReallocate: false,
        );

        $remaining = $classification->amount;
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

        $applied = $classification->amount - $remaining;
        $allocationStatus = FinanceCreditEntitlement::ALLOCATION_AVAILABLE;
        if ($applied >= $classification->amount && $classification->amount > 0) {
            $allocationStatus = FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED;
        } elseif ($applied > 0) {
            $allocationStatus = FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED;
        }

        $entitlement->forceFill(['allocation_status' => $allocationStatus])->save();

        // Invariant: grant-like conversion must not create new discount allocations
        // for the same reduction (credit applications are the sole carrier).
        $postDiscountAllocationSum = $this->discountAllocationsOnInvoices($invoiceIds);
        if (abs($postDiscountAllocationSum - $preDiscountAllocationSum) > self::RECONCILE_TOLERANCE) {
            throw new RuntimeException(
                "Scholarship credit conversion changed discount allocation sum for charge #{$charge->id} ({$preDiscountAllocationSum} → {$postDiscountAllocationSum}); refusing dual-carrier representation."
            );
        }

        $postSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $mismatch = $this->reconcileSnapshots($preSnapshots, $postSnapshots);

        if ($mismatch !== null) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($charge, 'mismatch', $mismatch, [
                'entitlement_id' => $entitlement->id,
                'application_ids' => $applicationIds,
                'carrier' => ScholarshipCarrierClassification::CARRIER_CREDIT,
                'pre' => $preSnapshots,
                'post' => $postSnapshots,
            ]);

            throw new RuntimeException(
                "Scholarship credit conversion reconciliation failed for charge #{$charge->id}: {$mismatch}"
            );
        }

        $summary['converted']++;
        $summary['converted_credit']++;
        $summary['details'][] = $this->detail($charge, 'converted_credit', 'ok', [
            'entitlement_id' => $entitlement->id,
            'application_ids' => $applicationIds,
            'applied_amount' => $applied,
            'classification' => $classification->toArray(),
        ]);
    }

    private function creditEntitlementExists(string $sourceRef): bool
    {
        return FinanceCreditEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', self::SOURCE_KIND)
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
            ->exists();
    }

    private function discountEntitlementExists(string $sourceRef): bool
    {
        return FinanceDiscountEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', self::SOURCE_KIND)
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
            ->exists();
    }

    /**
     * @param  Collection<int, InvoiceDiscount>  $carrierDiscounts
     */
    private function refreshDiscountAllocationStatus(
        FinanceDiscountEntitlement $entitlement,
        Collection $carrierDiscounts,
    ): void {
        $allocated = max(0.0, (float) DiscountAllocation::query()
            ->whereIn('invoice_discount_id', $carrierDiscounts->pluck('id'))
            ->sum('amount'));

        $amount = (float) $entitlement->amount;
        $status = FinanceDiscountEntitlement::ALLOCATION_AVAILABLE;

        if ($allocated >= $amount && $amount > 0) {
            $status = FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED;
        } elseif ($allocated > 0) {
            $status = FinanceDiscountEntitlement::ALLOCATION_PARTIALLY_ALLOCATED;
        }

        $entitlement->forceFill(['allocation_status' => $status])->save();
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
     */
    private function creditApplicationsOnInvoices(array $invoiceIds): int
    {
        if ($invoiceIds === []) {
            return 0;
        }

        $lineIds = InvoiceLine::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return 0;
        }

        return (int) CreditApplication::query()
            ->whereIn('invoice_line_id', $lineIds)
            ->count();
    }

    /**
     * @param  list<int>  $invoiceIds
     */
    private function discountAllocationsOnInvoices(array $invoiceIds): float
    {
        if ($invoiceIds === []) {
            return 0.0;
        }

        $lineIds = InvoiceLine::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return 0.0;
        }

        return (float) DiscountAllocation::query()
            ->whereIn('invoice_line_id', $lineIds)
            ->sum('amount');
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
            'source_kind' => self::SOURCE_KIND,
            'source_ref' => FinanceOwnedObligationSource::legacyScholarshipCreditChargeRef((int) $charge->id),
        ], $extra);
    }
}
