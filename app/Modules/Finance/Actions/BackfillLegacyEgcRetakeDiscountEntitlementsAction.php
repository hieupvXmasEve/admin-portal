<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Link legacy egc_retake InvoiceDiscount rows to FinanceDiscountEntitlement
 * (ADR-0030 / wave 5). Does not rewrite allocations; settlement remaining must
 * stay unchanged. Idempotent on re-run.
 */
class BackfillLegacyEgcRetakeDiscountEntitlementsAction
{
    private const RECONCILE_TOLERANCE = 0.01;

    public const SOURCE_KIND = 'legacy_egc_retake';

    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
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

            InvoiceDiscount::query()
                ->where('discount_type', ApplyEgcRetakeDiscountAction::DISCOUNT_TYPE)
                ->where(function ($statusQuery): void {
                    $statusQuery->whereNull('status')->orWhere('status', 'active');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(100, function ($discounts) use (&$summary, $dryRun): void {
                    foreach ($discounts as $discount) {
                        $summary['checked']++;
                        $this->convertDiscount($discount, $summary, $dryRun);
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
    private function convertDiscount(InvoiceDiscount $discount, array &$summary, bool $dryRun): void
    {
        $sourceRef = FinanceOwnedObligationSource::legacyEgcRetakeDiscountRef((int) $discount->id);

        if ($discount->finance_discount_entitlement_id !== null) {
            $summary['already_converted']++;
            $summary['details'][] = $this->detail($discount, 'already_converted', 'entitlement_linked', [
                'entitlement_id' => (int) $discount->finance_discount_entitlement_id,
            ]);

            return;
        }

        $existing = FinanceDiscountEntitlement::query()
            ->where('source_system', FinanceOwnedObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', self::SOURCE_KIND)
            ->where('source_ref', $sourceRef)
            ->where('entitlement_type', ObligationTypeRegistry::TYPE_EGC_RETAKE)
            ->first();

        if ($existing instanceof FinanceDiscountEntitlement) {
            if (! $dryRun) {
                $discount->forceFill([
                    'finance_discount_entitlement_id' => $existing->id,
                ])->save();
            }
            $summary['already_converted']++;
            $summary['details'][] = $this->detail($discount, 'already_converted', 'entitlement_exists', [
                'entitlement_id' => $existing->id,
            ]);

            return;
        }

        $invoice = StudentInvoice::query()->find($discount->invoice_id);
        if (! $invoice instanceof StudentInvoice || $invoice->student_id === null) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($discount, 'skipped', 'missing_invoice_student');

            return;
        }

        $discountAmount = abs((float) $discount->amount);
        if ($discountAmount <= 0) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($discount, 'skipped', 'non_positive_amount');

            return;
        }

        $allocated = (float) DiscountAllocation::query()
            ->where('invoice_discount_id', $discount->id)
            ->sum('amount');

        if ($allocated <= self::RECONCILE_TOLERANCE) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($discount, 'skipped', 'no_positive_allocations', [
                'allocated' => $allocated,
            ]);

            return;
        }

        $preSnapshot = $this->captureInvoiceSnapshot($invoice);

        if ($dryRun) {
            $summary['converted']++;
            $summary['details'][] = $this->detail($discount, 'would_convert', 'dry_run', [
                'discount_amount' => $discountAmount,
                'allocated' => $allocated,
                'pre_remaining' => $preSnapshot['remaining'],
            ]);

            return;
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $invoice->student_id);

        $entitlement = FinanceDiscountEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => FinanceOwnedObligationSource::SOURCE_SYSTEM,
            'source_kind' => self::SOURCE_KIND,
            'source_ref' => $sourceRef,
            'entitlement_type' => ObligationTypeRegistry::TYPE_EGC_RETAKE,
            'lifecycle_status' => FinanceDiscountEntitlement::STATUS_APPROVED,
            'allocation_status' => $this->allocationStatus($discountAmount, $allocated),
            'amount' => $discountAmount,
            'currency' => 'VND',
            'pricing_rule_version' => 'egc_retake:legacy_backfill',
            'pricing_snapshot' => [
                'pricing_strategy' => 'legacy_backfill',
                'legacy_invoice_discount_id' => $discount->id,
                'legacy_amount' => (float) $discount->amount,
                'reference_id' => $discount->reference_id,
                'description' => $discount->description,
            ],
            'approved_at' => now(),
        ]);

        $discount->forceFill([
            'finance_discount_entitlement_id' => $entitlement->id,
        ])->save();

        $postSnapshot = $this->captureInvoiceSnapshot($invoice->fresh() ?? $invoice);

        if (abs($preSnapshot['remaining'] - $postSnapshot['remaining']) > self::RECONCILE_TOLERANCE) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($discount, 'mismatch', 'remaining_drift', [
                'entitlement_id' => $entitlement->id,
                'pre' => $preSnapshot,
                'post' => $postSnapshot,
            ]);

            throw new RuntimeException(
                "EGC retake discount conversion reconciliation failed for invoice_discount #{$discount->id}: remaining drift"
            );
        }

        $summary['converted']++;
        $summary['details'][] = $this->detail($discount, 'converted', 'ok', [
            'entitlement_id' => $entitlement->id,
            'allocated' => $allocated,
        ]);
    }

    private function allocationStatus(float $amount, float $allocated): string
    {
        if ($allocated >= $amount && $amount > 0) {
            return FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED;
        }

        if ($allocated > 0) {
            return FinanceDiscountEntitlement::ALLOCATION_PARTIALLY_ALLOCATED;
        }

        return FinanceDiscountEntitlement::ALLOCATION_AVAILABLE;
    }

    /**
     * @return array{net:float,remaining:float,paid:float,discount:float,credit:float}
     */
    private function captureInvoiceSnapshot(StudentInvoice $invoice): array
    {
        $snapshot = $this->settlementService->deriveInvoiceSnapshot($invoice);

        return [
            'net' => (float) $snapshot['net'],
            'remaining' => (float) $snapshot['remaining'],
            'paid' => (float) $snapshot['paid'],
            'discount' => (float) $snapshot['discount'],
            'credit' => (float) ($snapshot['credit'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function detail(InvoiceDiscount $discount, string $status, string $reason, array $extra = []): array
    {
        return array_merge([
            'invoice_discount_id' => $discount->id,
            'invoice_id' => $discount->invoice_id,
            'discount_type' => $discount->discount_type,
            'discount_amount' => (float) $discount->amount,
            'status' => $status,
            'reason' => $reason,
            'source_kind' => self::SOURCE_KIND,
            'source_ref' => FinanceOwnedObligationSource::legacyEgcRetakeDiscountRef((int) $discount->id),
        ], $extra);
    }
}
