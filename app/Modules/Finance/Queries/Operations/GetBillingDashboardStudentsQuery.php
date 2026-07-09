<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\Student;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class GetBillingDashboardStudentsQuery
{
    public function __construct(
        protected SettlementService $settlementService,
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
            ->where(function (Builder $q) use ($semesterId) {
                $q->whereHas('courseRegistrations', fn ($sq) => $sq->where('semester_id', $semesterId)->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn']));
                $q->orWhereHas('deferCases', fn ($sq) => $sq->where('semester_id', $semesterId));
                $q->orWhereHas('invoices', fn ($sq) => $sq->where('semester_id', $semesterId));
            });

        if (! empty($search)) {
            $studentsQuery->where(function (Builder $query) use ($search) {
                $query->where('students.full_name', 'like', '%'.$search.'%')
                    ->orWhere('students.student_id', 'like', '%'.$search.'%');
            });
        }

        if ($stage !== 'all') {
            if ($stage === 'egc') {
                $studentsQuery->where('students.status', 'intake_pre_uni_gc');
            } elseif ($stage === 'major') {
                $studentsQuery->where('students.status', 'intake_course');
            }
        }

        if ($defer !== 'all') {
            $studentsQuery->whereExists(function ($query) use ($semesterId, $defer) {
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
            $studentsQuery->whereHas('courseRegistrations', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)->where('is_retake', true);
            });
        }

        $sort = $filters['sort'] ?? 'full_name';
        $direction = $filters['direction'] ?? 'asc';
        $students = $studentsQuery->with('intakeSemester:id,name')->get();
        $studentIds = $students->pluck('id');

        $semesterInvoices = StudentInvoice::query()
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id');

        $invoiceSnapshots = $semesterInvoices
            ->flatten(1)
            ->mapWithKeys(fn (StudentInvoice $invoice) => [
                $invoice->id => $this->settlementService->deriveInvoiceSnapshot($invoice),
            ]);

        $paymentsByStudent = Payment::query()
            ->with('applications')
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get()
            ->groupBy('student_id');

        $mappedStudents = $students->map(function ($student) use ($semesterId, $semesterInvoices, $paymentsByStudent, $invoiceSnapshots, $status) {
            $studentInvoices = $semesterInvoices->get($student->id, collect());
            $payments = $paymentsByStudent->get($student->id, collect());

            $grossBilled = (float) $studentInvoices->sum(fn (StudentInvoice $invoice) => $invoiceSnapshots[$invoice->id]['gross']);
            $discounts = (float) $studentInvoices->sum(fn (StudentInvoice $invoice) => $invoiceSnapshots[$invoice->id]['discount']);
            $netDue = (float) $studentInvoices->sum(fn (StudentInvoice $invoice) => $invoiceSnapshots[$invoice->id]['net']);
            $cashApplied = (float) $studentInvoices->sum(fn (StudentInvoice $invoice) => $invoiceSnapshots[$invoice->id]['paid']);
            $remaining = max(0, $netDue - $cashApplied);
            $totalPayments = (float) $payments->sum('amount');
            $totalAppliedAcrossPayments = (float) $payments->sum(fn ($payment) => max(0, (float) $payment->applications->sum('amount')));
            $unappliedCash = max(0, $totalPayments - $totalAppliedAcrossPayments);

            $latestInvoice = $studentInvoices->first();
            $flags = $this->getStudentBillingFlags($student->id, $semesterId);

            if ($studentInvoices->isEmpty()) {
                $flags['uncharged'] = true;
            }

            if ($status !== 'all') {
                if ($status === 'no_invoice' && ! $studentInvoices->isEmpty()) {
                    return null;
                }

                if ($status !== 'no_invoice' && ! $studentInvoices->contains(fn ($invoice) => ($invoiceSnapshots[$invoice->id]['status'] === $status) || ($status === 'unpaid' && in_array($invoiceSnapshots[$invoice->id]['status'], ['draft', 'pending', 'overdue'], true)))) {
                    return null;
                }
            }

            $stage = 'Unknown';
            if ($student->status === 'intake_pre_uni_gc') {
                $stage = 'EGC';
            } elseif ($student->status === 'intake_course') {
                $stage = 'Major';
            }

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program_code' => $student->program_code,
                'intake_semester' => $student->intakeSemester?->name,
                'stage' => $stage,
                'gc_current_level' => $student->gc_current_level,
                'status' => $student->status,
                'gross_billed' => $grossBilled,
                'total_discounts' => $discounts,
                'net_due' => $netDue,
                'cash_applied' => $cashApplied,
                'outstanding_amount' => $remaining,
                'unapplied_cash' => $unappliedCash,
                'breakdown' => [
                    'major' => (float) $studentInvoices->flatMap->invoiceLines->filter(fn ($line) => $line->charge?->charge_type === 'tuition_term')->sum('amount_snapshot'),
                    'egc' => (float) $studentInvoices->flatMap->invoiceLines->filter(fn ($line) => $line->charge?->charge_type === 'egc_level_fee')->sum('amount_snapshot'),
                    'retake' => (float) $studentInvoices->flatMap->invoiceLines->filter(fn ($line) => $line->charge?->charge_type === 'retake_fee')->sum('amount_snapshot'),
                    'discounts' => $discounts,
                ],
                'flags' => $flags,
                'invoices' => $studentInvoices->map(fn (StudentInvoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoiceSnapshots[$invoice->id]['status'],
                    'due_date' => $invoice->due_date?->toDateString(),
                    'total_amount' => $invoiceSnapshots[$invoice->id]['net'],
                    'paid_amount' => $invoiceSnapshots[$invoice->id]['paid'],
                ])->values()->all(),
                'invoice_statuses' => $studentInvoices->map(fn (StudentInvoice $invoice) => $invoiceSnapshots[$invoice->id]['status'])->unique()->values()->all(),
                'invoice_status' => $latestInvoice ? $invoiceSnapshots[$latestInvoice->id]['status'] : null,
                'invoice_number' => $latestInvoice?->invoice_number,
                'invoice_id' => $latestInvoice?->id,
                'due_date' => $latestInvoice?->due_date?->toDateString(),
            ];
        })->filter()->values();

        $mappedStudents = $this->sortStudents($mappedStudents, $sort, $direction);

        return $this->paginateCollection($mappedStudents, $perPage, $page, $filters);
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
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => route('finance.operations.dashboard'),
            'query' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]))->withQueryString();
    }

    private function getStudentBillingFlags(int $studentId, ?int $semesterId): array
    {
        $deferCase = DeferCase::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

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
