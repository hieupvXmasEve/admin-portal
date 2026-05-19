<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Facades\DB;

class VoidFinanceChargeAction
{
    public function __construct(
        protected AutoAllocatePaymentsAction $autoAllocatePaymentsAction,
        protected SettlementService $settlementService,
    ) {}

    /**
     * Void an existing charge, release allocations, and remove from invoice.
     *
     * @return array{charge: FinanceCharge, released_allocations: int, released_amount: float, affected_payments: array, reallocated_allocations: int, reallocated_amount: float}
     */
    public function handle(int $chargeId, string $reason, ?int $userId = null, bool $autoReallocate = true): array
    {
        return DB::transaction(function () use ($chargeId, $reason, $userId, $autoReallocate) {
            $charge = FinanceCharge::findOrFail($chargeId);
            $actorId = $userId ?? auth()->id();

            if ($charge->status === FinanceCharge::STATUS_VOID) {
                throw new \RuntimeException("Charge #{$chargeId} is already voided.");
            }

            $invoiceLines = InvoiceLine::query()
                ->where('charge_id', $charge->id)
                ->where('status', 'active')
                ->get();

            $releasedInfo = [
                'count' => 0,
                'amount' => 0.0,
                'payment_ids' => [],
            ];

            $affectedInvoiceIds = [];

            foreach ($invoiceLines as $line) {
                $lineRelease = $this->settlementService->releaseLinePayments(
                    $line,
                    $actorId,
                    self::class,
                    $charge->id,
                );

                $releasedInfo['count'] += $lineRelease['count'];
                $releasedInfo['amount'] += $lineRelease['amount'];
                $releasedInfo['payment_ids'] = array_values(array_unique(array_merge(
                    $releasedInfo['payment_ids'],
                    $lineRelease['payment_ids'],
                )));

                $releasedDiscountIds = $this->settlementService->releaseLineDiscounts(
                    $line,
                    self::class,
                    $charge->id,
                );

                $line->update([
                    'status' => 'void',
                    'voided_at' => now(),
                    'void_reason' => $reason,
                ]);

                foreach ($releasedDiscountIds as $discountId) {
                    $discount = InvoiceDiscount::query()->find($discountId);

                    if ($discount) {
                        $this->settlementService->synchronizeDiscountAllocations($discount);
                    }
                }

                $affectedInvoiceIds[] = (int) $line->invoice_id;

                $invoice = $line->invoice()->first();

                if ($invoice) {
                    $overpaymentRelease = $this->settlementService->releaseInvoiceOverpayments(
                        $invoice,
                        $actorId,
                        self::class,
                        $charge->id,
                    );

                    $releasedInfo['count'] += $overpaymentRelease['count'];
                    $releasedInfo['amount'] += $overpaymentRelease['amount'];
                    $releasedInfo['payment_ids'] = array_values(array_unique(array_merge(
                        $releasedInfo['payment_ids'],
                        $overpaymentRelease['payment_ids'],
                    )));
                }
            }

            $charge->update([
                'status' => FinanceCharge::STATUS_VOID,
                'voided_at' => now(),
                'voided_by_user_id' => $actorId,
                'void_reason' => $reason,
            ]);

            // 4. Recalculate affected invoice statuses
            $this->recalculateAffectedInvoices($affectedInvoiceIds);

            // 5. Re-allocate newly released balances to the student's remaining unpaid invoices
            $reallocatedStats = $autoReallocate && $releasedInfo['amount'] > 0
                ? $this->autoAllocatePaymentsAction->runForStudents(
                    [$charge->student_id],
                    AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER,
                    $actorId
                )
                : [
                    'allocations_created' => 0,
                    'total_allocated_amount' => 0,
                ];

            return [
                'charge' => $charge->fresh(),
                'released_allocations' => $releasedInfo['count'],
                'released_amount' => $releasedInfo['amount'],
                'affected_payments' => $releasedInfo['payment_ids'],
                'reallocated_allocations' => $reallocatedStats['allocations_created'],
                'reallocated_amount' => (float) $reallocatedStats['total_allocated_amount'],
            ];
        });
    }

    /**
     * Recalculate statuses for invoices that had charges removed.
     * If an invoice has no remaining active lines, cancel it.
     */
    protected function recalculateAffectedInvoices(array $invoiceIds): void
    {
        if (empty($invoiceIds)) {
            return;
        }

        $invoices = StudentInvoice::whereIn('id', array_unique($invoiceIds))
            ->with('invoiceLines')
            ->get();

        foreach ($invoices as $invoice) {
            $hasActiveLines = $invoice->invoiceLines
                ->contains(fn (InvoiceLine $line) => ($line->status ?? 'active') === 'active');

            if (! $hasActiveLines && $invoice->status !== 'cancelled') {
                $invoice->update(['status' => 'cancelled']);
            } else {
                $this->settlementService->recalculateInvoiceSnapshot($invoice);
            }
        }
    }
}
