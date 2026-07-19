<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Export;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\Reporting\SettlementReportAsOfContext;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

final class InvoiceExport implements FromQuery, WithHeadings, WithMapping
{
    /** @var array<int, SettlementPosition> */
    private array $positions = [];

    /** @var array<int, StudentReference> */
    private array $studentReferences = [];

    public function __construct(
        private readonly array $filters,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly CurrentSettlementPositionPresenter $positionPresenter,
        private readonly ?SettlementReportAsOfContext $reportContext = null,
    ) {}

    public function query(): Builder
    {
        /** @var StudentReferenceReader $studentReferences */
        $studentReferences = app(StudentReferenceReader::class);
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $campusStudentIds = $campusId === null ? null : $studentReferences->idsForCampus((int) $campusId);
        $search = trim((string) ($this->filters['search'] ?? ''));
        $matchingStudentIds = $search === '' ? [] : $studentReferences->idsMatchingSearch($search, $campusId === null ? null : (int) $campusId);

        return StudentInvoice::query()
            ->with('semester')
            ->when($campusStudentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $campusStudentIds))
            ->when(! empty($this->filters['semester_id']), fn (Builder $query) => $query->forSemester((int) $this->filters['semester_id']))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhereIn('student_id', $matchingStudentIds)))
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
        /** @var StudentReferenceReader $studentReferences */
        $studentReferences = app(StudentReferenceReader::class);
        $this->studentReferences = $studentReferences->findMany(
            $rows->pluck('student_id')->map(static fn (int|string $studentId): int => (int) $studentId)->all(),
        );

        if ($rows->isNotEmpty()) {
            $positions = $this->settlementPositionReader->batch(
                $rows->map(fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice(
                    (int) $invoice->id,
                    $this->reportContext?->asOf(),
                ))->all(),
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
            'Position Mode',
            'As Of Timestamp',
            'As Of Timezone',
            'Business-Effective Timestamp Rules',
        ];
    }

    public function map($invoice): array
    {
        $student = $this->studentReferences[(int) $invoice->student_id] ?? null;
        $position = $this->positions[(int) $invoice->id] ?? null;
        $amounts = $position?->amounts;
        $valid = $position?->isValid() && $amounts !== null;

        $presented = $position === null ? null : $this->positionPresenter->present($position);
        $context = $this->reportContext;

        return [
            $invoice->invoice_number,
            $student?->fullName,
            $student?->studentCode,
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
            $context?->mode ?? SettlementReportAsOfContext::MODE_CURRENT,
            $context?->asOf()?->toIso8601String(),
            $context?->timezone ?? (string) config('app.timezone', 'UTC'),
            $context === null ? 'Current committed ledger at captured settlement version.' : implode(' ', $context->payload()['business_effective_timestamp_rules']),
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
        $reportAt = $this->reportContext?->asOf() ?? now();
        if ($invoice->due_date !== null && $invoice->due_date->lessThan($reportAt)) {
            return 'overdue';
        }

        return 'open';
    }
}
