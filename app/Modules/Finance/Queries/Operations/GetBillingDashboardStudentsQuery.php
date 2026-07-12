<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class GetBillingDashboardStudentsQuery
{
    public function __construct(
        private readonly SettlementPositionWorklistReader $positionReader,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
    ) {}

    public function handle(?int $semesterId, array $filters = []): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $status = $filters['status'] ?? 'all';
        $stage = $filters['stage'] ?? 'all';
        $defer = $filters['defer'] ?? 'all';
        $retake = $filters['retake'] ?? 'all';
        $search = $filters['search'] ?? '';
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        if (! $semesterId) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $studentsQuery = Student::query()
            ->select([
                'students.id',
                'students.student_id',
                'students.full_name',
                'students.status',
                'programs.code as program_code',
                'students.intake_semester_id',
                'students.intake_gc',
                'students.intake_course',
                'students.intake_major',
                'students.gc_current_level',
            ])
            ->leftJoin('programs', 'students.program_id', '=', 'programs.id')
            ->where('students.intake_semester_id', '<=', $semesterId)
            ->when($campusId, fn ($q) => $q->where('students.campus_id', $campusId))
            ->where(function (Builder $q) use ($semesterId): void {
                $q->whereHas('courseRegistrations', fn ($sq) => $sq->where('semester_id', $semesterId)->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn']))
                    ->orWhereHas('deferCases', fn ($sq) => $sq->where('semester_id', $semesterId))
                    ->orWhereHas('invoices', fn ($sq) => $sq->where('semester_id', $semesterId));
            });

        if ($search !== '') {
            $studentsQuery->where(fn (Builder $query) => $query
                ->where('students.full_name', 'like', '%'.$search.'%')
                ->orWhere('students.student_id', 'like', '%'.$search.'%'));
        }

        if ($stage !== 'all') {
            $studentsQuery->where('students.status', $stage === 'egc' ? 'intake_pre_uni_gc' : 'intake_course');
        }

        if ($defer !== 'all') {
            $studentsQuery->whereExists(function ($query) use ($semesterId, $defer): void {
                $query->selectRaw('1')
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_id', 'students.id')
                    ->where('defer_cases.semester_id', $semesterId)
                    ->when($defer === 'preserve', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_PRESERVE))
                    ->when($defer === 'forfeit', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_FORFEIT))
                    ->when($defer === 'missing_docs', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_PRESERVE)->whereNull('upload_record_id'));
            });
        }

        if ($retake !== 'all') {
            $studentsQuery->whereHas('courseRegistrations', fn ($query) => $query->where('semester_id', $semesterId)->where('is_retake', true));
        }

        $sort = $filters['sort'] ?? 'full_name';
        $direction = $filters['direction'] ?? 'asc';
        $students = $studentsQuery->with('intakeSemester:id,name')->get();
        $studentIds = $students->pluck('id')->map(fn ($id): int => (int) $id);

        $semesterInvoices = StudentInvoice::query()
            ->with(['invoiceLines' => fn ($query) => $query->where('status', 'active')->with('charge.financeObligation')])
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id');

        $lineIdsByStudent = [];
        $lineIdsByInvoice = [];
        $lineIdsByStudentChargeType = [];

        foreach ($semesterInvoices->flatten(1) as $invoice) {
            $lineIdsByInvoice[(int) $invoice->id] = $invoice->invoiceLines->pluck('id')->map(fn ($id): int => (int) $id)->all();

            foreach ($invoice->invoiceLines as $line) {
                $studentId = (int) $invoice->student_id;
                $lineId = (int) $line->id;
                $chargeType = (string) ($line->charge?->charge_type ?? '');
                $lineIdsByStudent[$studentId][] = $lineId;
                $lineIdsByStudentChargeType[$studentId.'|'.$chargeType][] = $lineId;
            }
        }

        $positionsByStudent = $this->positionReader->forLineGroups($lineIdsByStudent);
        $positionsByInvoice = $this->positionReader->forLineGroups($lineIdsByInvoice);
        $positionsByStudentChargeType = $this->positionReader->forLineGroups($lineIdsByStudentChargeType);

        $paymentsByStudent = Payment::query()
            ->with('applications')
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get()
            ->groupBy('student_id');

        $mappedStudents = $students->map(function (Student $student) use (
            $semesterId,
            $semesterInvoices,
            $positionsByStudent,
            $positionsByInvoice,
            $positionsByStudentChargeType,
            $paymentsByStudent,
            $status,
        ): ?array {
            $studentInvoices = $semesterInvoices->get($student->id, collect());
            $position = $positionsByStudent[$student->id] ?? null;
            $summary = $position instanceof SettlementPosition
                ? $this->positionPresenter->summarize($position)
                : $this->missingSummary();
            $payments = $paymentsByStudent->get($student->id, collect());
            $unappliedCash = $this->positionPresenter->unappliedCash($payments);
            $invoiceSummaries = $studentInvoices->mapWithKeys(function (StudentInvoice $invoice) use ($positionsByInvoice): array {
                $invoicePosition = $positionsByInvoice[(int) $invoice->id] ?? null;
                $invoiceSummary = $invoicePosition instanceof SettlementPosition
                    ? $this->positionPresenter->summarize($invoicePosition)
                    : $this->missingSummary();

                return [$invoice->id => [
                    'summary' => $invoiceSummary,
                    'status' => $this->invoiceStatus($invoice, $invoiceSummary),
                ]];
            });

            if ($status !== 'all') {
                if ($status === 'no_invoice' && $studentInvoices->isNotEmpty()) {
                    return null;
                }

                if ($status !== 'no_invoice' && ! $invoiceSummaries->contains(fn (array $invoice) => $invoice['status'] === $status || ($status === 'unpaid' && in_array($invoice['status'], ['pending', 'overdue', 'partial'], true)))) {
                    return null;
                }
            }

            $stage = match ($student->status) {
                'intake_pre_uni_gc' => 'EGC',
                'intake_course' => 'Major',
                default => 'Unknown',
            };

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program_code' => $student->program_code,
                'intake_semester' => $student->intakeSemester?->name,
                'stage' => $stage,
                'gc_current_level' => $student->gc_current_level,
                'status' => $student->status,
                'gross_billed' => $summary['gross'],
                'total_discounts' => $summary['discount'],
                'net_due' => $summary['net'],
                'cash_applied' => $summary['cash'],
                'credit_applied' => $summary['credit'],
                'outstanding_amount' => $summary['remaining'],
                'unapplied_cash' => $unappliedCash,
                'settlement_state' => $summary['settlement_state'],
                'settlement_label' => $this->positionPresenter->summarize($position instanceof SettlementPosition ? $position : $this->missingPosition(), $unappliedCash > 0)['settlement_label'],
                'settlement_issues' => $summary['issues'],
                'needs_review' => ! $summary['valid'],
                'breakdown' => $this->breakdown($student->id, $positionsByStudentChargeType),
                'flags' => $this->getStudentBillingFlags($student->id, $semesterId),
                'invoices' => $studentInvoices->map(function (StudentInvoice $invoice) use ($invoiceSummaries): array {
                    $invoiceSummary = $invoiceSummaries[$invoice->id];

                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoiceSummary['status'],
                        'settlement_state' => $invoiceSummary['summary']['settlement_state'],
                        'settlement_label' => $invoiceSummary['summary']['settlement_label'],
                        'due_date' => $invoice->due_date?->toDateString(),
                        'total_amount' => $invoiceSummary['summary']['net'],
                        'paid_amount' => $invoiceSummary['summary']['cash'],
                        'remaining_amount' => $invoiceSummary['summary']['remaining'],
                    ];
                })->values()->all(),
                'invoice_statuses' => $invoiceSummaries->pluck('status')->unique()->values()->all(),
                'invoice_status' => $invoiceSummaries->first()['status'] ?? null,
                'invoice_number' => $studentInvoices->first()?->invoice_number,
                'invoice_id' => $studentInvoices->first()?->id,
                'due_date' => $studentInvoices->first()?->due_date?->toDateString(),
            ];
        })->filter()->values();

        $mappedStudents = $this->sortStudents($mappedStudents, $sort, $direction);

        return $this->paginateCollection($mappedStudents, $perPage, $page, $filters);
    }

    private function invoiceStatus(StudentInvoice $invoice, array $summary): string
    {
        if (! $summary['valid']) {
            return 'needs_review';
        }

        if ($summary['remaining'] <= 0) {
            return 'paid';
        }

        if ($invoice->due_date?->isPast()) {
            return 'overdue';
        }

        return $summary['cash'] > 0 || $summary['credit'] > 0 || $summary['discount'] > 0 ? 'partial' : 'pending';
    }

    /** @return array{major:?float,egc:?float,retake:?float,discount:?float} */
    private function breakdown(int $studentId, array $positions): array
    {
        return [
            'major' => $this->chargeTypeAmount($studentId, FinanceCharge::TYPE_TUITION_TERM, $positions),
            'egc' => $this->chargeTypeAmount($studentId, FinanceCharge::TYPE_EGC_LEVEL_FEE, $positions),
            'retake' => $this->chargeTypeAmount($studentId, FinanceCharge::TYPE_RETAKE_FEE, $positions),
            'discount' => $this->chargeTypeDiscount($studentId, $positions),
        ];
    }

    private function chargeTypeAmount(int $studentId, string $chargeType, array $positions): ?float
    {
        if (! array_key_exists($studentId.'|'.$chargeType, $positions)) {
            return 0.0;
        }

        $summary = $this->positionSummaryForKey($studentId.'|'.$chargeType, $positions);

        return $summary['valid'] ? $summary['gross'] : null;
    }

    private function chargeTypeDiscount(int $studentId, array $positions): ?float
    {
        $discount = 0.0;
        $found = false;

        foreach ($positions as $key => $position) {
            if (! str_starts_with((string) $key, $studentId.'|')) {
                continue;
            }

            $summary = $this->positionPresenter->summarize($position);
            if (! $summary['valid']) {
                return null;
            }
            $discount += $summary['discount'];
            $found = true;
        }

        return $found ? $discount : 0.0;
    }

    /** @return array<string,mixed> */
    private function positionSummaryForKey(string $key, array $positions): array
    {
        foreach ($positions as $positionKey => $position) {
            if ((string) $positionKey === $key) {
                return $this->positionPresenter->summarize($position);
            }
        }

        return $this->missingSummary();
    }

    /** @return array<string,mixed> */
    private function missingSummary(): array
    {
        return [
            'valid' => false,
            'settlement_state' => SettlementPosition::STATE_MISSING,
            'settlement_label' => 'Cần kiểm tra',
            'gross' => null,
            'discount' => null,
            'cash' => null,
            'credit' => null,
            'net' => null,
            'remaining' => null,
            'issues' => [[
                'code' => 'settlement_position.missing_payable_line',
                'severity' => 'blocking',
                'blocking' => true,
                'evidence' => [],
                'finance_invariant_code' => null,
            ]],
        ];
    }

    private function missingPosition(): SettlementPosition
    {
        return SettlementPosition::invalid(
            scopeType: SettlementPosition::SCOPE_PAYABLE_LINE,
            scopeId: 0,
            payableLineId: null,
            financeObligationId: null,
            rawEvidence: SettlementPositionRawEvidence::empty(),
            issues: [],
        );
    }

    private function sortStudents(Collection $students, string $sort, string $direction): Collection
    {
        $descending = $direction === 'desc';

        return match ($sort) {
            'gross_billed' => $students->sortBy('gross_billed', SORT_REGULAR, $descending)->values(),
            'cash_applied' => $students->sortBy('cash_applied', SORT_REGULAR, $descending)->values(),
            'outstanding_amount', 'remaining_balance' => $students->sortBy('outstanding_amount', SORT_REGULAR, $descending)->values(),
            'total_discounts' => $students->sortBy('total_discounts', SORT_REGULAR, $descending)->values(),
            default => $students->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE, $descending)->values(),
        };
    }

    private function paginateCollection(Collection $items, int $perPage, int $page, array $filters): LengthAwarePaginator
    {
        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => route('finance.operations.dashboard'),
            'query' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]))->withQueryString();
    }

    /** @return array<string,bool> */
    private function getStudentBillingFlags(int $studentId, ?int $semesterId): array
    {
        $deferCase = DeferCase::where('student_id', $studentId)->where('semester_id', $semesterId)->first();
        $hasRetake = \DB::table('course_registrations')
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('is_retake', true)
            ->exists();

        return [
            'has_retake' => $hasRetake,
            'is_defer_preserve' => $deferCase?->fee_policy === DeferCase::POLICY_PRESERVE,
            'is_defer_forfeit' => $deferCase?->fee_policy === DeferCase::POLICY_FORFEIT,
            'missing_docs' => $deferCase && $deferCase->fee_policy === DeferCase::POLICY_PRESERVE && ! $deferCase->upload_record_id,
            'uncharged' => false,
        ];
    }
}
