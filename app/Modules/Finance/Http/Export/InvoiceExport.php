<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Export;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class InvoiceExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<int, SettlementPosition> */
    private array $positions = [];

    public function __construct(
        private readonly array $filters,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly CurrentSettlementPositionPresenter $positionPresenter,
    ) {}

    public function query(): Builder
    {
        return StudentInvoice::query()
            ->with(['student', 'semester'])
            ->when(app()->bound('campus') && app('campus')?->id, fn (Builder $query) => $query->forCampus((int) app('campus')->id))
            ->when(! empty($this->filters['semester_id']), fn (Builder $query) => $query->forSemester((int) $this->filters['semester_id']))
            ->when(! empty($this->filters['search']), fn (Builder $query) => $query->search((string) $this->filters['search']))
            ->latest();
    }

    /**
     * Maatwebsite calls this once per query chunk. One batch reader call keeps
     * exported rows on one current settlement snapshot without N+1 reads.
     */
    public function prepareRows(iterable $rows): iterable
    {
        $rows = collect($rows);
        $this->positions = [];

        if ($rows->isNotEmpty()) {
            $positions = $this->settlementPositionReader->batch(
                $rows->map(fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice((int) $invoice->id))->all(),
            );

            foreach ($rows->values() as $index => $invoice) {
                if (isset($positions[$index])) {
                    $this->positions[(int) $invoice->id] = $positions[$index];
                }
            }
        }

        $status = (string) ($this->filters['status'] ?? 'all');
        if ($status !== 'all' && $status !== '') {
            $rows = $rows->filter(fn (StudentInvoice $invoice): bool => $this->statusFor(
                $invoice,
                $this->positions[(int) $invoice->id] ?? null,
            ) === $status)->values();
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Student Name',
            'Student ID',
            'Semester',
            'Gross Amount',
            'Discount',
            'Cash Received',
            'Credit Applied',
            'Remaining Collectible',
            'Settlement State',
            'Settlement Version',
            'Issue Codes',
            'Payable Line Breakdown',
            'Due Date',
        ];
    }

    public function map($invoice): array
    {
        $position = $this->positions[(int) $invoice->id] ?? null;
        $amounts = $position?->amounts;
        $valid = $position?->isValid() && $amounts !== null;

        $presented = $position === null ? null : $this->positionPresenter->present($position);

        return [
            $invoice->invoice_number,
            $invoice->student->full_name,
            $invoice->student->student_id,
            $invoice->semester->name,
            $valid ? $amounts->gross->amount : null,
            $valid ? $amounts->discount->amount : null,
            $valid ? $amounts->cash->amount : null,
            $valid ? $amounts->credit->amount : null,
            $valid ? $amounts->remaining->amount : null,
            $valid ? $position->settlement_state : SettlementPosition::STATE_INVALID,
            $position?->snapshot_version,
            $position === null ? 'settlement_position.missing_payable_line' : implode(',', array_map(
                static fn ($issue): string => $issue['code'],
                $this->positionPresenter->issues($position->issues),
            )),
            $presented === null ? '[]' : json_encode($presented['payable_line_breakdown'], JSON_THROW_ON_ERROR),
            $invoice->due_date?->format('Y-m-d'),
        ];
    }

    private function statusFor(StudentInvoice $invoice, ?SettlementPosition $position): string
    {
        $amounts = $position?->amounts;
        if (! $position?->isValid() || $amounts === null) {
            return SettlementPosition::STATE_INVALID;
        }
        if ($amounts->gross->isZero()) {
            return 'zero_amount';
        }
        if ($amounts->remaining->minor_amount <= 1) {
            return 'paid';
        }
        if ($invoice->due_date?->isPast()) {
            return 'overdue';
        }

        return 'open';
    }
}
