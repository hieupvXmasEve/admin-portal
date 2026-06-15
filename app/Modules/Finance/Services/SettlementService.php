<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SettlementService
{
    /** Tolerance for comparing cached invoice columns against derived balances. */
    public const CACHE_DRIFT_TOLERANCE = 0.01;

    /**
     * Whether an invoice's cached columns disagree with its derived snapshot.
     * Single source of the cache-drift formula shared by the audit graph query
     * and the audit warning builder so they can never diverge.
     */
    public function invoiceCacheDrifts(StudentInvoice $invoice): bool
    {
        return $this->snapshotDriftsFromCache($invoice, $this->deriveInvoiceSnapshot($invoice));
    }

    /**
     * Same comparison as invoiceCacheDrifts() but against an already-computed
     * snapshot, so callers that need the derived numbers do not derive twice.
     *
     * @param  array{net:float,paid:float}  $snapshot
     */
    public function snapshotDriftsFromCache(StudentInvoice $invoice, array $snapshot): bool
    {
        return abs((float) $invoice->cached_paid_amount - (float) $snapshot['paid']) > self::CACHE_DRIFT_TOLERANCE
            || abs((float) $invoice->cached_total_amount - (float) $snapshot['net']) > self::CACHE_DRIFT_TOLERANCE;
    }

    public function deriveInvoiceSnapshot(StudentInvoice $invoice): array
    {
        $invoice->loadMissing([
            'invoiceLines.charge',
            'invoiceLines.paymentApplications',
            'invoiceLines.discountAllocations.invoiceDiscount',
        ]);

        $activeLines = $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
            ->values();

        $gross = (float) $activeLines
            ->where('amount_snapshot', '>', 0)
            ->sum('amount_snapshot');

        // FIN-02 defensive backstop: the ledger is signed, so a released discount
        // already nets via its negative row. This additionally excludes any
        // allocation whose parent discount is reversed, so a future path that
        // flips status without writing the offsetting row cannot inflate balance.
        $discount = (float) $activeLines
            ->sum(fn (InvoiceLine $line) => max(0, (float) $line->discountAllocations
                ->reject(fn (DiscountAllocation $allocation) => $this->isReversedDiscountAllocation($allocation))
                ->sum('amount')));

        if ($discount === 0.0) {
            $discount = abs((float) $activeLines
                ->where('amount_snapshot', '<', 0)
                ->sum('amount_snapshot'));
        }

        $net = max(0, $gross - $discount);

        $paid = max(0, (float) $activeLines
            ->sum(fn (InvoiceLine $line) => max(0, (float) $line->paymentApplications->sum('amount'))));

        $paid = min($paid, $net);
        $remaining = max(0, $net - $paid);

        $status = $invoice->status === 'draft' ? 'draft' : 'pending';

        if ($net <= 0 || $remaining <= 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        } elseif ($invoice->due_date && $invoice->due_date->isPast()) {
            $status = 'overdue';
        }

        return [
            'gross' => $gross,
            'discount' => $discount,
            'net' => $net,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $status,
        ];
    }

    public function getPaymentAllocatedAmount(Payment $payment): float
    {
        return max(0, (float) PaymentApplication::query()
            ->where('payment_id', $payment->id)
            ->sum('amount'));
    }

    public function getPaymentUnappliedAmount(Payment $payment): float
    {
        return max(0, (float) $payment->amount - $this->getPaymentAllocatedAmount($payment));
    }

    public function getChargePaidAmount(int $chargeId): float
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $chargeId)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return 0.0;
        }

        return max(0, (float) PaymentApplication::query()
            ->whereIn('invoice_line_id', $lineIds)
            ->sum('amount'));
    }

    /**
     * Sum of discount allocations (scholarship/voucher) applied to a charge's invoice lines.
     */
    public function getChargeDiscountAmount(int $chargeId): float
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $chargeId)
            ->pluck('id');

        if ($lineIds->isEmpty()) {
            return 0.0;
        }

        return max(0, (float) DiscountAllocation::query()
            ->whereIn('invoice_line_id', $lineIds)
            ->where(fn ($query) => $this->excludeReversedDiscountAllocations($query))
            ->sum('amount'));
    }

    public function getLineDiscountAmount(InvoiceLine $line): float
    {
        // FIN-02: keep this consistent with deriveInvoiceSnapshot's discount
        // backstop — a reversed discount must not reduce line outstanding, or
        // allocation would stop early while the invoice still shows the balance.
        return max(0, (float) DiscountAllocation::query()
            ->where('invoice_line_id', $line->id)
            ->where(fn ($query) => $this->excludeReversedDiscountAllocations($query))
            ->sum('amount'));
    }

    public function getLinePaidAmount(InvoiceLine $line): float
    {
        return max(0, (float) PaymentApplication::query()
            ->where('invoice_line_id', $line->id)
            ->sum('amount'));
    }

    public function getLineNetDue(InvoiceLine $line): float
    {
        return max(0, (float) $line->amount_snapshot - $this->getLineDiscountAmount($line));
    }

    public function getLineOutstandingAmount(InvoiceLine $line): float
    {
        return max(0, $this->getLineNetDue($line) - $this->getLinePaidAmount($line));
    }

    /**
     * Terminal lifecycle statuses owned by admin actions, not by payment state.
     * recalculateInvoiceSnapshot must never overwrite these from the derived
     * payment status (it would resurrect a cancelled/voided invoice).
     */
    private const TERMINAL_LIFECYCLE_STATUSES = ['cancelled', 'void'];

    public function recalculateInvoiceSnapshot(StudentInvoice $invoice): void
    {
        $snapshot = $this->deriveInvoiceSnapshot($invoice);

        $isPaid = $snapshot['status'] === 'paid' && $snapshot['paid'] > 0;

        // DB-15: keep the first moment the invoice became paid, and clear the
        // cached timestamp the moment a reversal makes it no longer paid — never
        // leave a stale paid_at on a reopened invoice.
        $cachedPaidAt = $isPaid ? ($invoice->cached_paid_at ?? now()) : null;

        // status is a lifecycle column, not cache: preserve terminal states so a
        // cache rebuild (or any recalc) never flips a cancelled/voided invoice
        // back into the payment lifecycle.
        $status = in_array($invoice->status, self::TERMINAL_LIFECYCLE_STATUSES, true)
            ? $invoice->status
            : $snapshot['status'];

        $invoice->forceFill([
            'cached_subtotal' => $snapshot['gross'],
            'cached_discount_total' => $snapshot['discount'],
            'cached_total_amount' => $snapshot['net'],
            'cached_paid_amount' => $snapshot['paid'],
            'status' => $status,
            'cached_paid_at' => $cachedPaidAt,
        ])->save();
    }

    public function createPaymentApplication(
        Payment $payment,
        InvoiceLine $line,
        float $amount,
        string $entryType,
        ?int $userId = null,
        ?string $sourceRefType = null,
        ?int $sourceRefId = null,
    ): PaymentApplication {
        $signedAmount = $entryType === 'reversal'
            ? -abs($amount)
            : abs($amount);

        $application = PaymentApplication::query()->create([
            'payment_id' => $payment->id,
            'invoice_line_id' => $line->id,
            'amount' => $signedAmount,
            'entry_type' => $entryType,
            'source_ref_type' => $sourceRefType,
            'source_ref_id' => $sourceRefId,
            'applied_at' => now(),
            'created_by' => $userId,
        ]);

        $this->recalculateInvoiceSnapshot($line->invoice()->firstOrFail());

        return $application;
    }

    public function releaseLinePayments(InvoiceLine $line, ?int $userId = null, ?string $sourceRefType = null, ?int $sourceRefId = null): array
    {
        $netByPayment = PaymentApplication::query()
            ->selectRaw('payment_id, SUM(amount) as net_amount')
            ->where('invoice_line_id', $line->id)
            ->groupBy('payment_id')
            ->havingRaw('SUM(amount) > 0')
            ->get();

        $releasedAmount = 0.0;
        $releasedCount = 0;
        $paymentIds = [];

        foreach ($netByPayment as $row) {
            $payment = Payment::query()->find($row->payment_id);

            if (! $payment) {
                continue;
            }

            $amount = (float) $row->net_amount;

            $this->createPaymentApplication(
                $payment,
                $line,
                $amount,
                'reversal',
                $userId,
                $sourceRefType,
                $sourceRefId,
            );

            $releasedAmount += $amount;
            $releasedCount++;
            $paymentIds[] = (int) $payment->id;
        }

        return [
            'count' => $releasedCount,
            'amount' => $releasedAmount,
            'payment_ids' => array_values(array_unique($paymentIds)),
        ];
    }

    public function releaseInvoiceOverpayments(StudentInvoice $invoice, ?int $userId = null, ?string $sourceRefType = null, ?int $sourceRefId = null): array
    {
        $released = [
            'count' => 0,
            'amount' => 0.0,
            'payment_ids' => [],
        ];

        $lines = InvoiceLine::query()
            ->with('charge')
            ->where('invoice_id', $invoice->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
            ->values();

        foreach ($lines as $line) {
            $lineReleased = $this->releaseLineOverpayment(
                $line,
                $userId,
                $sourceRefType,
                $sourceRefId,
            );

            $released['count'] += $lineReleased['count'];
            $released['amount'] += $lineReleased['amount'];
            $released['payment_ids'] = array_values(array_unique(array_merge(
                $released['payment_ids'],
                $lineReleased['payment_ids'],
            )));
        }

        return $released;
    }

    public function createOrRefreshInvoiceDiscount(
        StudentInvoice $invoice,
        string $discountType,
        float $amount,
        ?string $discountSource = null,
        ?string $description = null,
        ?int $referenceId = null,
        ?int $approvedBy = null,
    ): InvoiceDiscount {
        $discount = InvoiceDiscount::query()->firstOrNew([
            'invoice_id' => $invoice->id,
            'discount_type' => $discountType,
            'reference_id' => $referenceId,
            'discount_source' => $discountSource ?? $discountType,
        ]);

        $discount->fill([
            'description' => $description ?? $discountType,
            'amount' => abs($amount),
            'status' => 'active',
            'approved_by' => $approvedBy,
        ]);
        $discount->save();

        $this->synchronizeDiscountAllocations($discount);

        return $discount;
    }

    public function releaseLineDiscounts(InvoiceLine $line, ?string $sourceRefType = null, ?int $sourceRefId = null): array
    {
        $netByDiscount = DiscountAllocation::query()
            ->selectRaw('invoice_discount_id, SUM(amount) as net_amount')
            ->where('invoice_line_id', $line->id)
            ->groupBy('invoice_discount_id')
            ->havingRaw('SUM(amount) > 0')
            ->get();

        $released = [];

        foreach ($netByDiscount as $row) {
            DiscountAllocation::query()->create([
                'invoice_discount_id' => $row->invoice_discount_id,
                'invoice_line_id' => $line->id,
                'amount' => -abs((float) $row->net_amount),
                'entry_type' => 'release',
                'source_ref_type' => $sourceRefType,
                'source_ref_id' => $sourceRefId,
                'allocation_rule' => 'current_line_chronology',
            ]);

            $released[] = (int) $row->invoice_discount_id;
        }

        return array_values(array_unique($released));
    }

    public function synchronizeDiscountAllocations(InvoiceDiscount $discount): void
    {
        $invoice = $discount->invoice()->first();

        if (! $invoice) {
            return;
        }

        $eligibleLines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($eligibleLines->isEmpty()) {
            $this->recalculateInvoiceSnapshot($invoice);

            return;
        }

        $netAllocated = max(0, (float) DiscountAllocation::query()
            ->where('invoice_discount_id', $discount->id)
            ->sum('amount'));

        $remaining = max(0, (float) $discount->amount - $netAllocated);

        foreach ($eligibleLines as $line) {
            if ($remaining <= 0) {
                break;
            }

            $lineCurrentDiscount = $this->getLineDiscountAmount($line);
            $lineCapacity = max(0, (float) $line->amount_snapshot - $lineCurrentDiscount);

            if ($lineCapacity <= 0) {
                continue;
            }

            $allocateAmount = min($remaining, $lineCapacity);

            DiscountAllocation::query()->create([
                'invoice_discount_id' => $discount->id,
                'invoice_line_id' => $line->id,
                'amount' => $allocateAmount,
                'entry_type' => 'allocation',
                'allocation_rule' => 'current_line_chronology',
            ]);

            $remaining -= $allocateAmount;
        }

        $this->recalculateInvoiceSnapshot($invoice);
    }

    public function releaseLineOverpayment(InvoiceLine $line, ?int $userId = null, ?string $sourceRefType = null, ?int $sourceRefId = null): array
    {
        $excess = $this->getLinePaidAmount($line) - $this->getLineNetDue($line);

        if ($excess <= 0) {
            return [
                'count' => 0,
                'amount' => 0.0,
                'payment_ids' => [],
            ];
        }

        $netByPayment = PaymentApplication::query()
            ->selectRaw('payment_id, MAX(applied_at) as last_applied_at, SUM(amount) as net_amount')
            ->where('invoice_line_id', $line->id)
            ->groupBy('payment_id')
            ->havingRaw('SUM(amount) > 0')
            ->orderByDesc('last_applied_at')
            ->get();

        $releasedAmount = 0.0;
        $releasedCount = 0;
        $paymentIds = [];

        foreach ($netByPayment as $row) {
            if ($excess <= 0) {
                break;
            }

            $payment = Payment::query()->find($row->payment_id);

            if (! $payment) {
                continue;
            }

            $releaseAmount = min($excess, (float) $row->net_amount);

            $this->createPaymentApplication(
                $payment,
                $line,
                $releaseAmount,
                'reversal',
                $userId,
                $sourceRefType,
                $sourceRefId,
            );

            $excess -= $releaseAmount;
            $releasedAmount += $releaseAmount;
            $releasedCount++;
            $paymentIds[] = (int) $payment->id;
        }

        return [
            'count' => $releasedCount,
            'amount' => $releasedAmount,
            'payment_ids' => array_values(array_unique($paymentIds)),
        ];
    }

    public function getOutstandingLinesForStudent(int $studentId, array $priorityOrder): Collection
    {
        $lines = InvoiceLine::query()
            ->with(['invoice', 'charge'])
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->whereHas('invoice', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('status', '!=', 'paid');
            })
            ->whereHas('charge', function ($query) {
                $query->where('status', 'active')
                    ->where('amount', '>', 0);
            })
            ->get();

        $priorityRank = [];
        foreach (array_values($priorityOrder) as $index => $chargeType) {
            $priorityRank[$chargeType] = $index;
        }
        $fallbackRank = count($priorityRank);

        return $lines
            ->filter(fn (InvoiceLine $line) => $this->getLineOutstandingAmount($line) > 0)
            ->sortBy(function (InvoiceLine $line) use ($priorityRank, $fallbackRank) {
                $invoice = $line->invoice;
                $chargeType = $line->charge?->charge_type ?? '';
                $priority = $priorityRank[$chargeType] ?? $fallbackRank;

                return [
                    $priority,
                    optional($invoice?->due_date)?->getTimestamp() ?? PHP_INT_MAX,
                    optional($invoice?->created_at)?->getTimestamp() ?? 0,
                    $invoice?->id ?? 0,
                    optional($line->created_at)?->getTimestamp() ?? 0,
                    $line->id,
                ];
            })
            ->values();
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE;
    }

    /**
     * Defensive backstop for FIN-02: an allocation belonging to a discount whose
     * parent record is marked reversed must not count toward balance, even if the
     * offsetting negative allocation row was never written.
     */
    private function isReversedDiscountAllocation(DiscountAllocation $allocation): bool
    {
        return ($allocation->invoiceDiscount?->status ?? 'active') === 'reversed';
    }

    /**
     * Query-builder twin of isReversedDiscountAllocation(): keep allocations
     * whose parent discount is missing or not reversed. Used by the line-level
     * discount sums so they agree with deriveInvoiceSnapshot.
     *
     * @param  Builder<DiscountAllocation>  $query
     */
    private function excludeReversedDiscountAllocations($query): void
    {
        $query->whereDoesntHave('invoiceDiscount', fn ($discountQuery) => $discountQuery->where('status', 'reversed'));
    }
}
