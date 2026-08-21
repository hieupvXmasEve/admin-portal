<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Support\Facades\DB;

class VoidFinanceChargeAction
{
    public function __construct(
        protected AutoAllocatePaymentsAction $autoAllocatePaymentsAction,
        protected SettlementService $settlementService,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    /**
     * Void an existing charge, release allocations, and remove from invoice.
     *
     * Auto-reallocation of the freed cash is intentionally controllable via
     * $autoReallocate (FIN-12): callers that want a void to be inert (no hidden
     * spread of released payments onto other invoices) pass false. The result
     * always reports what was reallocated so the side-effect is never silent.
     *
     * @return array{charge: FinanceCharge, released_allocations: int, released_amount: float, affected_payments: array, reallocated_allocations: int, reallocated_amount: float, cancelled_installments: int}
     */
    public function handle(
        int $chargeId,
        string $reason,
        ?int $userId = null,
        bool $autoReallocate = true,
        bool $recalculateInvoices = true,
    ): array {
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) FinanceCharge::query()->findOrFail($chargeId)->student_id)
            ->id;

        return $this->settlementMutationGuard->handle($billingAccountId, function () use ($chargeId, $reason, $userId, $autoReallocate, $recalculateInvoices): array {
            return DB::transaction(function () use ($chargeId, $reason, $userId, $autoReallocate, $recalculateInvoices): array {
                $charge = FinanceCharge::query()->lockForUpdate()->findOrFail($chargeId);
                $actorId = $userId ?? auth()->id();

                if ($charge->status === FinanceCharge::STATUS_VOID) {
                    throw new \RuntimeException("Charge #{$chargeId} is already voided.");
                }

                // FIN-12: do not unilaterally void a charge that still has a LIVE DNG
                // request — the provider may still hold a collectible record. Block and
                // steer the operator to the cancellation operation flow, which cancels
                // the provider collection first and then voids the charge safely.
                $this->assertNoLiveDngCollection($charge);

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
                            $this->settlementService->synchronizeDiscountAllocations($discount, recalculateInvoice: false);
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

                // FIN-12: a voided charge must not keep live installments. Cancel
                // non-settled rows so no next-installment DNG can still be pushed and
                // no invariant flags a live row on a dead charge.
                $cancelledInstallments = $this->cancelChargeInstallments($charge);

                // 4. Recalculate affected invoice statuses
                if ($recalculateInvoices) {
                    $this->recalculateAffectedInvoices($affectedInvoiceIds);
                }

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
                    'cancelled_installments' => $cancelledInstallments,
                ];
            });
        });
    }

    /**
     * Block the void when the charge has a live DNG collection request (FIN-12).
     * "Live" is any HOLDING_COLLECTION_STATUSES status, not just pending/pushed:
     * unknown_outcome and needs_review mean Swinx does not yet know whether the
     * provider cleared the record, so a void must not proceed unchecked either
     * (CancelDngPaymentRequestAction::runLocallyForLifecycle() already accepts
     * the same four statuses).
     *
     * Requests are resolved through both the charge pivot
     * (dng_payment_request_charges — the general link, including multi-charge
     * and no-installment requests) and the installment link
     * (finance_charge_installments.dng_payment_request_id — for requests
     * pushed before the pivot table existed).
     *
     * A multi-charge request is blocked, not cancelled inline: cancelling it
     * here would kill the collection for sibling charges too. The error
     * message points the operator at the staff DNG cancel screen rather than
     * naming an internal class — cancelling there releases every linked
     * installment back to pending, so the remaining charges' payable
     * re-surfaces for the next reservation instead of staying silently
     * uncollected.
     */
    protected function assertNoLiveDngCollection(FinanceCharge $charge): void
    {
        $installmentRequestIds = FinanceChargeInstallment::query()
            ->where('finance_charge_id', $charge->id)
            ->whereNotNull('dng_payment_request_id')
            ->pluck('dng_payment_request_id');

        $liveRequest = DngPaymentRequest::query()
            ->holdingCollection()
            ->where(function ($query) use ($charge, $installmentRequestIds): void {
                $query->whereHas('chargeLinks', fn ($q) => $q->where('finance_charge_id', $charge->id))
                    ->orWhereIn('id', $installmentRequestIds);
            })
            ->orderBy('id')
            ->first();

        if ($liveRequest !== null) {
            throw new \RuntimeException(
                "Charge #{$charge->id} has a live DNG collection request (#{$liveRequest->id}, "
                ."status: {$liveRequest->status}). Cancel the DNG request first (DNG Payment "
                .'Requests screen → Cancel), then void the charge.'
            );
        }
    }

    /**
     * Cancel a voided charge's installments (FIN-12).
     *
     * - pending / awaiting_payment → cancelled (no longer collectible).
     * - paid → cancelled only when the charge's payments net to zero, i.e. the
     *   void already fully reversed them; otherwise the paid row is preserved as
     *   a settled fact.
     *
     * @return int number of installments cancelled
     */
    protected function cancelChargeInstallments(FinanceCharge $charge): int
    {
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $charge->student_id)
            ->id;

        return $this->settlementMutationGuard->handle($billingAccountId, function () use ($charge): int {
            $installments = FinanceChargeInstallment::query()
                ->where('finance_charge_id', $charge->id)
                ->get();

            if ($installments->isEmpty()) {
                return 0;
            }

            // The charge and its lines may already be void by this point, so the
            // current-position reader correctly rejects them as non-payable. The
            // cancellation decision instead reads the immutable cash evidence.
            $chargeNetPaid = max(0.0, (float) PaymentApplication::query()
                ->join('invoice_lines', 'invoice_lines.id', '=', 'payment_applications.invoice_line_id')
                ->where('invoice_lines.charge_id', $charge->id)
                ->sum('payment_applications.amount'));

            $cancelled = 0;

            foreach ($installments as $installment) {
                $isNonSettled = in_array($installment->status, [
                    FinanceChargeInstallment::STATUS_PENDING,
                    FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
                ], true);

                $isReversedPaid = $installment->status === FinanceChargeInstallment::STATUS_PAID
                    && $chargeNetPaid <= 0.0;

                if ($isNonSettled || $isReversedPaid) {
                    $installment->update(['status' => FinanceChargeInstallment::STATUS_CANCELLED]);
                    $cancelled++;
                }
            }

            return $cancelled;
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
                $billingAccountId = (int) $this->billingAccountProvisioner->forStudent((int) $invoice->student_id)->id;
                $this->settlementMutationGuard->handle($billingAccountId, function () use ($invoice): void {
                    $invoice->forceFill([
                        'cached_subtotal' => 0,
                        'cached_discount_total' => 0,
                        'cached_total_amount' => 0,
                        'cached_paid_amount' => 0,
                        'cached_paid_at' => null,
                        'status' => 'cancelled',
                    ])->save();
                });
            } else {
                $this->settlementService->recalculateInvoiceSnapshot($invoice);
            }
        }
    }
}
