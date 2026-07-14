<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Student360;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Collection;

/**
 * Read-only "Sổ cái" lens: student invoices grouped by semester, each with
 * its canonical Settlement Position and payable-line breakdown.
 */
class GetStudent360LedgerQuery
{
    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
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

        $positionsByInvoice = $this->settlementPositionReader->batch(
            $invoices->map(
                static fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice((int) $invoice->id),
            )->all(),
        );
        $positionsByInvoiceId = $invoices->values()->mapWithKeys(
            fn (StudentInvoice $invoice, int $index): array => [(int) $invoice->id => $positionsByInvoice[$index] ?? null],
        );

        return $invoices
            ->groupBy(fn (StudentInvoice $invoice) => $invoice->semester_id)
            ->map(fn (Collection $group) => $this->semesterGroupRow($group, $positionsByInvoiceId))
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function semesterGroupRow(Collection $group, Collection $positionsByInvoiceId): array
    {
        $lineIds = $group->flatMap(
            fn (StudentInvoice $invoice) => $invoice->invoiceLines->pluck('id')->map(static fn (mixed $id): int => (int) $id),
        )->all();
        $position = $this->settlementPositionReader->forPayableLines($lineIds);
        $valid = $position->isValid() && $position->amounts !== null;
        $invoices = $group
            ->map(fn (StudentInvoice $invoice) => $this->invoiceRow(
                $invoice,
                $positionsByInvoiceId->get((int) $invoice->id),
            ))
            ->values()
            ->all();

        return [
            'semester' => $this->semesterLabel($group->first()),
            'collectible_total' => $valid ? (float) $position->amounts->netDue()->amount : null,
            'collectible_paid' => $valid ? (float) $position->amounts->cash->amount : null,
            'collectible_credit' => $valid ? (float) $position->amounts->credit->amount : null,
            'collectible_remaining' => $valid ? (float) $position->amounts->remaining->amount : null,
            'state_label' => ! $valid ? 'Cần kiểm tra' : ($position->amounts->remaining->isPositive() ? 'Còn phải thu' : 'Không còn phải thu'),
            'settlement_valid' => $valid,
            'settlement_issues' => array_map(static fn ($issue): array => [
                'code' => $issue->code,
                'blocking' => $issue->blocking,
            ], $position->issues),
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
    private function invoiceRow(StudentInvoice $invoice, ?SettlementPosition $position): array
    {
        $valid = $position?->isValid() && $position->amounts !== null;
        $amounts = $position?->amounts;
        $linePositions = collect($position?->payable_line_breakdown ?? [])
            ->keyBy(fn (SettlementPosition $line): int => (int) $line->payable_line_id);

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'status' => $this->invoiceStatus($invoice, $position),
            'gross' => $valid ? (float) $amounts->gross->amount : null,
            'discount' => $valid ? (float) $amounts->discount->amount : null,
            'net' => $valid ? (float) $amounts->netDue()->amount : null,
            'paid' => $valid ? (float) $amounts->cash->amount : null,
            'credit' => $valid ? (float) $amounts->credit->amount : null,
            'remaining' => $valid ? (float) $amounts->remaining->amount : null,
            'due_date' => $invoice->due_date?->toIso8601String(),
            'paid_at' => $invoice->cached_paid_at?->toIso8601String(),
            'lines' => $invoice->invoiceLines
                ->sortBy('id')
                ->map(fn (InvoiceLine $line) => $this->lineRow($line, $linePositions->get((int) $line->id)))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function lineRow(InvoiceLine $line, ?SettlementPosition $position): array
    {
        $chargeType = (string) ($line->charge?->charge_type ?? 'manual_fee');
        $valid = $position?->isValid() && $position->amounts !== null;
        $amounts = $position?->amounts;
        $status = $this->lineStatus($line);

        return [
            'id' => (int) $line->id,
            'charge_type' => $chargeType,
            'label' => $chargeType,
            'description' => (string) ($line->description_snapshot ?: $line->charge?->description ?: ''),
            'amount' => $valid ? (float) $amounts->gross->amount : null,
            'discount' => $valid ? (float) $amounts->discount->amount : null,
            'paid' => $valid ? (float) $amounts->cash->amount : null,
            'credit' => $valid ? (float) $amounts->credit->amount : null,
            'outstanding' => $valid ? (float) $amounts->remaining->amount : null,
            'is_credit' => (float) $line->amount_snapshot < 0,
            'status' => $status,
            'status_label' => $status === 'void' ? 'Đã hủy' : 'Đang thu',
            'voided_at' => ($line->voided_at ?? $line->charge?->voided_at)?->toIso8601String(),
            'void_reason' => $line->void_reason ?: $line->charge?->void_reason,
            'payment_applied' => $this->sumLineApplications($line, true),
            'payment_reversed' => $this->sumLineApplications($line, false),
        ];
    }

    private function invoiceStatus(StudentInvoice $invoice, ?SettlementPosition $position): string
    {
        if (in_array($invoice->status, ['cancelled', 'void'], true)) {
            return (string) $invoice->status;
        }

        if (! $position?->isValid() || $position->amounts === null) {
            return SettlementPosition::STATE_INVALID;
        }

        if ($position->amounts->gross->isZero()) {
            return 'zero_amount';
        }

        if ($position->amounts->remaining->isZero()) {
            return 'paid';
        }

        return $invoice->due_date?->isPast() ? 'overdue' : 'open';
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
}
