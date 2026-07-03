<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Collection;

/**
 * Read-only "Sổ cái" lens: student invoices grouped by semester, each with
 * its derived snapshot and lines. All money figures come from SettlementService.
 */
class GetStudent360LedgerQuery
{
    public function __construct(
        private SettlementService $settlement,
    ) {}

    /** @return list<array<string,mixed>> */
    public function handle(int $studentId): array
    {
        $invoices = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->with(['semester:id,name,code', 'invoiceLines.charge', 'invoiceLines.paymentApplications'])
            ->orderByDesc('semester_id')
            ->orderBy('id')
            ->get();

        return $invoices
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->semester_id)
            ->map(fn (Collection $group) => $this->semesterGroupRow($group))
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function semesterGroupRow(Collection $group): array
    {
        $invoices = $group
            ->map(fn (StudentInvoice $invoice) => $this->invoiceRow($invoice))
            ->values()
            ->all();

        $remaining = (float) collect($invoices)->sum('remaining');

        return [
            'semester' => $this->semesterLabel($group->first()),
            'collectible_total' => (float) collect($invoices)->sum('net'),
            'collectible_paid' => (float) collect($invoices)->sum('paid'),
            'collectible_remaining' => $remaining,
            'state_label' => $remaining > 0 ? 'Còn phải thu' : 'Không còn phải thu',
            'invoices' => $invoices,
        ];
    }

    /** @return array{id:?int,code:?string,name:string} */
    private function semesterLabel(StudentInvoice $invoice): array
    {
        $semester = $invoice->semester;

        return [
            'id' => $semester?->id !== null ? (int) $semester->id : null,
            'code' => $semester?->code !== null ? (string) $semester->code : null,
            'name' => $semester?->name ?? 'Chưa gắn kỳ',
        ];
    }

    /** @return array<string,mixed> */
    private function invoiceRow(StudentInvoice $invoice): array
    {
        $snapshot = $this->settlement->deriveInvoiceSnapshot($invoice);

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'status' => $this->invoiceStatus($invoice, $snapshot),
            'gross' => (float) $snapshot['gross'],
            'discount' => (float) $snapshot['discount'],
            'net' => (float) $snapshot['net'],
            'paid' => (float) $snapshot['paid'],
            'remaining' => (float) $snapshot['remaining'],
            'due_date' => $invoice->due_date?->toIso8601String(),
            'paid_at' => $invoice->cached_paid_at?->toIso8601String(),
            'lines' => $invoice->invoiceLines
                ->sortBy('id')
                ->map(fn (InvoiceLine $line) => $this->lineRow($line))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function lineRow(InvoiceLine $line): array
    {
        $chargeType = (string) ($line->charge?->charge_type ?? 'manual_fee');
        $amount = (float) $line->amount_snapshot;
        $paid = $this->settlement->getLinePaidAmount($line);
        $isCollectible = $this->isBillableActiveLine($line);
        $status = $this->lineStatus($line);

        return [
            'id' => (int) $line->id,
            'charge_type' => $chargeType,
            'label' => $chargeType,
            'description' => (string) ($line->description_snapshot ?: $line->charge?->description ?: ''),
            'amount' => $amount,
            'paid' => $paid,
            'outstanding' => $isCollectible ? $this->settlement->getLineOutstandingAmount($line) : 0.0,
            'is_credit' => $amount < 0,
            'status' => $status,
            'status_label' => $status === 'void' ? 'Đã hủy' : 'Đang thu',
            'voided_at' => ($line->voided_at ?? $line->charge?->voided_at)?->toIso8601String(),
            'void_reason' => $line->void_reason ?: $line->charge?->void_reason,
            'payment_applied' => $this->sumLineApplications($line, true),
            'payment_reversed' => $this->sumLineApplications($line, false),
        ];
    }

    /** @param  array<string,mixed>  $snapshot */
    private function invoiceStatus(StudentInvoice $invoice, array $snapshot): string
    {
        if (in_array($invoice->status, ['cancelled', 'void'], true)) {
            return (string) $invoice->status;
        }

        return (string) $snapshot['status'];
    }

    private function lineStatus(InvoiceLine $line): string
    {
        if (($line->status ?? 'active') !== 'active') {
            return 'void';
        }

        return $line->charge?->status === FinanceCharge::STATUS_VOID ? 'void' : 'active';
    }

    private function sumLineApplications(InvoiceLine $line, bool $positive): float
    {
        $amounts = $line->paymentApplications->pluck('amount');

        if ($positive) {
            return (float) $amounts
                ->filter(fn (mixed $amount) => (float) $amount > 0)
                ->sum();
        }

        return abs((float) $amounts
            ->filter(fn (mixed $amount) => (float) $amount < 0)
            ->sum());
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE;
    }
}
