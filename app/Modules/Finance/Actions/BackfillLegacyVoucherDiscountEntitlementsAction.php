<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\VoucherApplication;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Convert legacy voucher_credit negative charges whose reduction already lives
 * in invoice_discounts / discount_allocations into FinanceDiscountEntitlement
 * (ADR-0030). Voids the duplicate negative line; never writes credit applications.
 */
class BackfillLegacyVoucherDiscountEntitlementsAction
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
                ->where('charge_type', FinanceCharge::TYPE_VOUCHER_CREDIT)
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

        $sourceRef = FinanceOwnedObligationSource::legacyVoucherCreditChargeRef((int) $charge->id);
        $existing = FinanceDiscountEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', 'legacy_voucher_credit')
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', FinanceCharge::TYPE_VOUCHER_CREDIT)
            ->first();

        if ($existing instanceof FinanceDiscountEntitlement) {
            $summary['already_converted']++;
            $summary['details'][] = $this->detail($charge, 'already_converted', 'entitlement_exists', [
                'entitlement_id' => $existing->id,
            ]);

            return;
        }

        $discountAmount = abs((float) $charge->amount);
        if ($discountAmount <= 0) {
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

        $carrierDiscounts = $this->findDiscountCarriers($charge, $invoiceIds);

        if ($carrierDiscounts->isEmpty()) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'no_discount_allocation_carrier', [
                'invoice_ids' => $invoiceIds,
            ]);

            return;
        }

        $preSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $preCreditApplicationCount = $this->creditApplicationsOnInvoices($invoiceIds);

        if ($dryRun) {
            $summary['converted']++;
            $summary['details'][] = $this->detail($charge, 'would_convert', 'dry_run', [
                'discount_amount' => $discountAmount,
                'invoice_ids' => $invoiceIds,
                'carrier_discount_ids' => $carrierDiscounts->pluck('id')->all(),
                'pre_remaining' => collect($preSnapshots)->map(fn (array $s) => $s['remaining'])->all(),
            ]);

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $charge->student_id);

        $entitlement = FinanceDiscountEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => 'legacy_voucher_credit',
            'source_ref' => $sourceRef,
            'entitlement_type' => FinanceCharge::TYPE_VOUCHER_CREDIT,
            'lifecycle_status' => FinanceDiscountEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceDiscountEntitlement::ALLOCATION_AVAILABLE,
            'amount' => $discountAmount,
            'currency' => 'VND',
            'pricing_rule_version' => 'voucher_credit:legacy_backfill',
            'pricing_snapshot' => [
                'pricing_strategy' => 'legacy_backfill',
                'legacy_charge_id' => $charge->id,
                'legacy_amount' => (float) $charge->amount,
                'description' => $charge->description,
                'carrier_invoice_discount_ids' => $carrierDiscounts->pluck('id')->values()->all(),
            ],
            'approved_at' => now(),
        ]);

        foreach ($carrierDiscounts as $discount) {
            $discount->forceFill([
                'finance_discount_entitlement_id' => $entitlement->id,
            ])->save();
        }

        // Void the duplicate negative line. Carrier discount allocations stay;
        // they are on positive tuition lines, not on the voucher credit line.
        $this->voidFinanceChargeAction->handle(
            chargeId: (int) $charge->id,
            reason: 'Converted to FinanceDiscountEntitlement (legacy voucher_credit backfill)',
            userId: null,
            autoReallocate: false,
        );

        $this->refreshAllocationStatus($entitlement, $carrierDiscounts);

        // Invariant: voucher conversion must not introduce credit applications
        // for the same reduction (discount carrier remains the only carrier).
        $postCreditApplicationCount = $this->creditApplicationsOnInvoices($invoiceIds);
        if ($postCreditApplicationCount !== $preCreditApplicationCount) {
            throw new RuntimeException(
                "Voucher conversion changed credit application count for charge #{$charge->id} ({$preCreditApplicationCount} → {$postCreditApplicationCount}); refusing dual-carrier representation."
            );
        }

        $postSnapshots = $this->captureInvoiceSnapshots($invoiceIds);
        $mismatch = $this->reconcileSnapshots($preSnapshots, $postSnapshots);

        if ($mismatch !== null) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($charge, 'mismatch', $mismatch, [
                'entitlement_id' => $entitlement->id,
                'carrier_discount_ids' => $carrierDiscounts->pluck('id')->all(),
                'pre' => $preSnapshots,
                'post' => $postSnapshots,
            ]);

            throw new RuntimeException(
                "Voucher discount conversion reconciliation failed for charge #{$charge->id}: {$mismatch}"
            );
        }

        $summary['converted']++;
        $summary['details'][] = $this->detail($charge, 'converted', 'ok', [
            'entitlement_id' => $entitlement->id,
            'carrier_discount_ids' => $carrierDiscounts->pluck('id')->all(),
        ]);
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return Collection<int, InvoiceDiscount>
     */
    private function findDiscountCarriers(FinanceCharge $charge, array $invoiceIds): Collection
    {
        if ($invoiceIds === []) {
            return collect();
        }

        $query = InvoiceDiscount::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->where('discount_type', 'voucher')
            ->where(function ($statusQuery): void {
                $statusQuery->whereNull('status')->orWhere('status', 'active');
            })
            // Already owned by another entitlement must not be rebound.
            ->where(function ($linkQuery): void {
                $linkQuery->whereNull('finance_discount_entitlement_id');
            });

        $voucherApp = VoucherApplication::query()
            ->where('finance_charge_id', $charge->id)
            ->first();

        if ($voucherApp instanceof VoucherApplication) {
            $byReference = (clone $query)
                ->where('reference_id', $voucherApp->id)
                ->get();

            $matched = $this->withPositiveNetAllocations($byReference);
            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        // Prefer exact amount match on the same invoice(s). Never fall back to
        // "all voucher discounts on invoice" — that can rebind unrelated carriers.
        $byAmount = (clone $query)
            ->where('amount', abs((float) $charge->amount))
            ->get();

        return $this->withPositiveNetAllocations($byAmount);
    }

    /**
     * @param  Collection<int, InvoiceDiscount>  $discounts
     * @return Collection<int, InvoiceDiscount>
     */
    private function withPositiveNetAllocations(Collection $discounts): Collection
    {
        return $discounts
            ->filter(function (InvoiceDiscount $discount): bool {
                $net = (float) DiscountAllocation::query()
                    ->where('invoice_discount_id', $discount->id)
                    ->sum('amount');

                return $net > self::RECONCILE_TOLERANCE;
            })
            ->values();
    }

    /**
     * @param  Collection<int, InvoiceDiscount>  $carrierDiscounts
     */
    private function refreshAllocationStatus(
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
            'source_kind' => 'legacy_voucher_credit',
            'source_ref' => FinanceOwnedObligationSource::legacyVoucherCreditChargeRef((int) $charge->id),
        ], $extra);
    }
}
