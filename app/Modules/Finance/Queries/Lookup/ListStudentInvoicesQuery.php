<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Lookup;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\FinanceSemesterContextResolver;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListStudentInvoicesQuery
{
    private const SORTABLE = ['invoice_number', 'due_date', 'created_at'];

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly CurrentSettlementPositionPresenter $positionPresenter,
    ) {}

    /** @return array{items: LengthAwarePaginator} */
    public function handle(Request $request): array
    {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : app('campus')?->id;
        $semesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : FinanceSemesterContextResolver::selectedId();

        $invoices = StudentInvoice::query()
            ->with(['student', 'semester', 'invoiceLines.charge'])
            ->when($campusId !== null, fn ($query) => $query->forCampus((int) $campusId))
            ->when($semesterId !== null, fn ($query) => $query->forSemester($semesterId))
            ->when($request->filled('search'), fn ($query) => $query->search((string) $request->input('search')))
            ->latest()
            ->get();

        $positions = $this->settlementPositions($invoices);
        $rows = $invoices->map(fn (StudentInvoice $invoice, int $index): array => $this->mapInvoice(
            $invoice,
            $positions[$index] ?? null,
        ));

        $status = (string) $request->input('status', 'all');
        if ($status !== 'all' && $status !== '') {
            $rows = $rows->filter(fn (array $row): bool => $row['real_time_status'] === $status)->values();
        }

        $sort = (string) $request->input('sort', '');
        if (in_array($sort, self::SORTABLE, true)) {
            $direction = $request->input('direction') === 'asc' ? 1 : -1;
            $rows = $rows->sort(fn (array $left, array $right): int => $direction * (($left[$sort] ?? '') <=> ($right[$sort] ?? '')))->values();
        }

        $perPage = in_array((int) $request->input('per_page', 50), [20, 50, 100], true)
            ? (int) $request->input('per_page', 50)
            : 50;
        $page = max(1, (int) $request->input('page', 1));

        return [
            'items' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
        ];
    }

    /** @param Collection<int, StudentInvoice> $invoices */
    private function settlementPositions(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        return $this->settlementPositionReader->batch(
            $invoices->map(fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice((int) $invoice->id))->all(),
        );
    }

    /** @return array<string, mixed> */
    private function mapInvoice(StudentInvoice $invoice, ?SettlementPosition $position): array
    {
        $amounts = $position?->amounts;
        $valid = $position?->isValid() && $amounts !== null;
        $gross = $valid ? (float) $amounts->gross->amount : null;
        $discount = $valid ? (float) $amounts->discount->amount : null;
        $cash = $valid ? (float) $amounts->cash->amount : null;
        $credit = $valid ? (float) $amounts->credit->amount : null;
        $remaining = $valid ? (float) $amounts->remaining->amount : null;

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'student_id' => (int) $invoice->student_id,
            'student' => [
                'id' => (int) $invoice->student->id,
                'full_name' => (string) $invoice->student->full_name,
                'student_id' => (string) $invoice->student->student_id,
            ],
            'semester' => ['id' => (int) $invoice->semester->id, 'name' => (string) $invoice->semester->name],
            'created_at' => $invoice->created_at?->toIso8601String(),
            'real_time_status' => $this->status($invoice, $valid, $gross, $remaining),
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'credit_amount' => $credit,
            'total_amount' => $valid ? round($gross - $discount, 2) : null,
            'paid_amount' => $cash,
            'outstanding_balance' => $remaining,
            'due_date' => $invoice->due_date?->toIso8601String(),
            'settlement_valid' => $valid,
            'settlement_state' => $valid ? $position->settlement_state : SettlementPosition::STATE_INVALID,
            'settlement_version' => $position?->snapshot_version,
            'settlement_issue_codes' => $position === null ? [
                'settlement_position.missing_payable_line',
            ] : array_values(array_unique(array_map(fn ($issue): string => $issue->code, $position->issues))),
            'settlement_issues' => $position === null ? [] : $this->positionPresenter->issues($position->issues),
            'settlement_breakdown' => $position === null ? [] : $this->positionPresenter->present($position),
        ];
    }

    private function status(StudentInvoice $invoice, bool $valid, ?float $gross, ?float $remaining): string
    {
        if (! $valid) {
            return SettlementPosition::STATE_INVALID;
        }
        if ($gross === 0.0) {
            return 'zero_amount';
        }
        if (($remaining ?? 0.0) <= 0.01) {
            return 'paid';
        }
        if ($invoice->due_date?->isPast()) {
            return 'overdue';
        }

        return 'open';
    }
}
