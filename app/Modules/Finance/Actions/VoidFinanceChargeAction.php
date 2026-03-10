<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class VoidFinanceChargeAction
{
    /**
     * Void an existing charge, release allocations, and remove from invoice.
     *
     * @return array{charge: FinanceCharge, released_allocations: int, affected_payments: array}
     */
    public function handle(int $chargeId, string $reason, ?int $userId = null): array
    {
        return DB::transaction(function () use ($chargeId, $reason, $userId) {
            $charge = FinanceCharge::findOrFail($chargeId);

            if ($charge->status === FinanceCharge::STATUS_VOID) {
                throw new \RuntimeException("Charge #{$chargeId} is already voided.");
            }

            // 1. Release all allocations for this charge
            $releasedInfo = $this->releaseAllocations($charge);

            // 2. Remove charge from invoice(s)
            $affectedInvoiceIds = $this->removeFromInvoice($charge);

            // 3. Void the charge
            $charge->update([
                'status' => FinanceCharge::STATUS_VOID,
                'voided_at' => now(),
                'voided_by_user_id' => $userId ?? auth()->id(),
                'void_reason' => $reason,
            ]);

            // 4. Recalculate affected invoice statuses
            $this->recalculateAffectedInvoices($affectedInvoiceIds);

            return [
                'charge' => $charge->fresh(),
                'released_allocations' => $releasedInfo['count'],
                'released_amount' => $releasedInfo['amount'],
                'affected_payments' => $releasedInfo['payment_ids'],
            ];
        });
    }

    /**
     * Release all payment allocations for a charge.
     *
     * @return array{count: int, amount: float, payment_ids: array}
     */
    protected function releaseAllocations(FinanceCharge $charge): array
    {
        $allocations = PaymentAllocation::where('charge_id', $charge->id)->get();

        $result = [
            'count' => $allocations->count(),
            'amount' => (float) $allocations->sum('allocated_amount'),
            'payment_ids' => $allocations->pluck('payment_id')->unique()->values()->toArray(),
        ];

        PaymentAllocation::where('charge_id', $charge->id)->delete();

        return $result;
    }

    /**
     * Remove charge from invoice(s) and clean up empty invoices.
     *
     * @return array Invoice IDs that were affected (for recalculation)
     */
    protected function removeFromInvoice(FinanceCharge $charge): array
    {
        $invoiceLines = InvoiceLine::where('charge_id', $charge->id)->get();
        $affectedInvoiceIds = $invoiceLines->pluck('invoice_id')->unique()->toArray();

        foreach ($invoiceLines as $line) {
            $invoiceId = $line->invoice_id;
            $line->delete();

            // If invoice has no more lines, delete it
            $remainingLines = InvoiceLine::where('invoice_id', $invoiceId)->count();
            if ($remainingLines === 0) {
                \App\Models\StudentInvoice::where('id', $invoiceId)->delete();
                $affectedInvoiceIds = array_diff($affectedInvoiceIds, [$invoiceId]);
            }
        }

        return array_values($affectedInvoiceIds);
    }

    /**
     * Recalculate statuses for invoices that had charges removed.
     */
    protected function recalculateAffectedInvoices(array $invoiceIds): void
    {
        if (empty($invoiceIds)) {
            return;
        }

        $invoices = \App\Models\StudentInvoice::whereIn('id', $invoiceIds)->get();
        foreach ($invoices as $invoice) {
            $this->recalculateInvoiceStatus($invoice);
        }
    }

    /**
     * Recalculate invoice status based on current lines and allocations.
     * Inlined to avoid circular dependency with InvoiceGenerationService.
     */
    protected function recalculateInvoiceStatus(\App\Models\StudentInvoice $invoice): void
    {
        $lines = $invoice->invoiceLines()->get();

        $subtotal = (float) $lines->where('amount_snapshot', '>', 0)->sum('amount_snapshot');
        $credits = abs((float) $lines->where('amount_snapshot', '<', 0)->sum('amount_snapshot'));
        $totalAmount = max(0, $subtotal - $credits);

        $chargeIds = $lines->pluck('charge_id');
        $paidAmount = (float) DB::table('payment_allocations')
            ->whereIn('charge_id', $chargeIds)
            ->sum('allocated_amount');

        if ($totalAmount <= 0 || $paidAmount >= $totalAmount) {
            $status = 'paid';
        } elseif ($paidAmount > 0) {
            $status = 'partial';
        } elseif ($invoice->due_date && $invoice->due_date->isPast()) {
            $status = 'overdue';
        } else {
            $status = $invoice->status === 'draft' ? 'draft' : 'pending';
        }

        $invoice->update(['status' => $status]);
    }
}
