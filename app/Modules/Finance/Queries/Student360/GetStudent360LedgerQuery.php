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
            ->with(['semester:id,name,code', 'invoiceLines.charge'])
            ->orderByDesc('semester_id')
            ->orderBy('id')
            ->get();

        return $invoices
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->semester_id)
            ->map(fn (Collection $group) => [
                'semester' => $this->semesterLabel($group->first()),
                'invoices' => $group->map(fn (StudentInvoice $invoice) => $this->invoiceRow($invoice))->values()->all(),
            ])
            ->values()
            ->all();
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
            'status' => $snapshot['status'],
            'gross' => (float) $snapshot['gross'],
            'discount' => (float) $snapshot['discount'],
            'net' => (float) $snapshot['net'],
            'paid' => (float) $snapshot['paid'],
            'remaining' => (float) $snapshot['remaining'],
            'due_date' => $invoice->due_date?->toIso8601String(),
            'paid_at' => $invoice->cached_paid_at?->toIso8601String(),
            'lines' => $invoice->invoiceLines
                ->filter(fn (InvoiceLine $line) => $this->isBillableActiveLine($line))
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

        return [
            'id' => (int) $line->id,
            'charge_type' => $chargeType,
            'label' => $chargeType,
            'description' => (string) ($line->description_snapshot ?: $line->charge?->description ?: ''),
            'amount' => $amount,
            'paid' => $paid,
            'outstanding' => $this->settlement->getLineOutstandingAmount($line),
            'is_credit' => $amount < 0,
        ];
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE;
    }
}
