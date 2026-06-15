<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

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

    /** @return array{id:?int,name:string} */
    private function semesterLabel(StudentInvoice $invoice): array
    {
        $semester = $invoice->semester;

        return [
            'id' => $semester?->id !== null ? (int) $semester->id : null,
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
            'net' => (float) $snapshot['net'],
            'paid' => (float) $snapshot['paid'],
            'remaining' => (float) $snapshot['remaining'],
            'lines' => $invoice->invoiceLines->map(fn (InvoiceLine $line) => [
                'id' => (int) $line->id,
                'label' => (string) ($line->charge?->charge_type ?? 'Dòng phí'),
                'outstanding' => $this->settlement->getLineOutstandingAmount($line),
            ])->all(),
        ];
    }
}
