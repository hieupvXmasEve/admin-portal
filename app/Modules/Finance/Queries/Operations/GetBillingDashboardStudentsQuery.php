<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetBillingDashboardStudentsQuery
{
    public function handle(?int $semesterId, array $filters = []): LengthAwarePaginator
    {
        $campusId = app('campus')?->id;
        $status = $filters['status'] ?? 'all';
        $stage = $filters['stage'] ?? 'all';
        $defer = $filters['defer'] ?? 'all';
        $retake = $filters['retake'] ?? 'all';
        $search = $filters['search'] ?? '';
        $perPage = (int) ($filters['per_page'] ?? 20);

        if (! $semesterId) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        // 1. Base Query with Eligible Scope
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
                $q->orWhereHas(
                    'financeCharges',
                    fn ($sq) => $sq->where('semester_id', $semesterId)->where('status', FinanceCharge::STATUS_ACTIVE)
                );
                $q->orWhereHas('invoices', fn ($sq) => $sq->where('semester_id', $semesterId));
            });

        // 2. Add finance aggregates via subqueries
        // Total Charged
        $studentsQuery->addSelect([
            'total_charged' => FinanceCharge::selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('semester_id', $semesterId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('amount', '>', 0),

            'total_credits' => FinanceCharge::selectRaw('COALESCE(SUM(ABS(amount)), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('semester_id', $semesterId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('amount', '<', 0),

            'total_paid' => DB::table('payment_allocations')
                ->join('finance_charges', 'payment_allocations.charge_id', '=', 'finance_charges.id')
                ->selectRaw('COALESCE(SUM(allocated_amount), 0)')
                ->whereColumn('finance_charges.student_id', 'students.id')
                ->where('finance_charges.semester_id', $semesterId)
                ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE),

            'unapplied_allocations' => DB::table('payment_allocations')
                ->join('finance_charges', 'payment_allocations.charge_id', '=', 'finance_charges.id')
                ->selectRaw('COALESCE(SUM(allocated_amount), 0)')
                ->whereColumn('finance_charges.student_id', 'students.id'),

            'total_payments' => DB::table('payments')
                ->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('status', Payment::STATUS_COMPLETED),
        ]);

        // Breakdown Subqueries
        $studentsQuery->addSelect([
            'major_fee' => FinanceCharge::selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('semester_id', $semesterId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM),

            'egc_fee' => FinanceCharge::selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('semester_id', $semesterId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE),

            'retake_fee' => FinanceCharge::selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('student_id', 'students.id')
                ->where('semester_id', $semesterId)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE),
        ]);

        // 3. Apply Filters
        // Search Filter (searches both name and student_id)
        if (! empty($search)) {
            $studentsQuery->where(function (Builder $query) use ($search) {
                $query->where('students.full_name', 'like', '%'.$search.'%')
                    ->orWhere('students.student_id', 'like', '%'.$search.'%');
            });
        }

        // Status Filter
        if (! empty($status) && $status !== 'all') {
            if ($status === 'no_invoice') {
                $studentsQuery->whereNotExists(function ($query) use ($semesterId) {
                    $query->select(DB::raw(1))
                        ->from('student_invoices')
                        ->whereColumn('student_invoices.student_id', 'students.id')
                        ->where('student_invoices.semester_id', $semesterId);
                });
            } elseif ($status === 'unpaid') {
                $studentsQuery->whereExists(function ($query) use ($semesterId) {
                    $query->select(DB::raw(1))
                        ->from('student_invoices')
                        ->whereColumn('student_invoices.student_id', 'students.id')
                        ->where('student_invoices.semester_id', $semesterId)
                        ->whereIn('status', ['draft', 'pending', 'overdue']);
                });
            } else {
                $studentsQuery->whereExists(function ($query) use ($semesterId, $status) {
                    $query->select(DB::raw(1))
                        ->from('student_invoices')
                        ->whereColumn('student_invoices.student_id', 'students.id')
                        ->where('student_invoices.semester_id', $semesterId)
                        ->where('status', $status);
                });
            }
        }

        // Stage Filter
        if ($stage !== 'all') {
            if ($stage === 'egc') {
                $studentsQuery->where('students.status', 'intake_pre_uni_gc');
            } elseif ($stage === 'major') {
                $studentsQuery->where('students.status', 'intake_course');
            }
        }

        // Defer Filter
        if ($defer !== 'all') {
            $studentsQuery->whereExists(function ($query) use ($semesterId, $defer) {
                $query->select(DB::raw(1))
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_id', 'students.id')
                    ->where('defer_cases.semester_id', $semesterId)
                    ->when($defer === 'preserve', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_PRESERVE))
                    ->when($defer === 'forfeit', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_FORFEIT))
                    ->when($defer === 'missing_docs', fn ($q) => $q->where('fee_policy', DeferCase::POLICY_PRESERVE)->whereNull('upload_record_id'));
            });
        }

        // Retake Filter
        if ($retake !== 'all') {
            $studentsQuery->whereHas('courseRegistrations', function ($query) use ($semesterId, $retake) {
                $query->where('semester_id', $semesterId)
                    ->where('is_retake', true)
                    ->when($retake === 'retake_unpaid', function ($q) {
                        // Complex: check if retake fee is unpaid. For now, just check if has retake registration.
                        // Actually, retake_unpaid usually means has retake reg but retake_fee is NOT in finance_charges or balance > 0
                    });
            });

            if ($retake === 'retake_unpaid') {
                // Example refinement: has retake reg BUT balance > 0 or something
                // For now keep it as "has retake registration"
            }
        }

        // Load intake semester name
        $studentsQuery->with('intakeSemester:id,name');

        // 4. Apply Sorting
        $sort = $filters['sort'] ?? null;
        $direction = $filters['direction'] ?? 'asc';

        if ($sort === 'full_name') {
            $studentsQuery->orderBy('students.full_name', $direction);
        } elseif ($sort === 'total_charged') {
            $studentsQuery->orderBy('total_charged', $direction);
        } elseif ($sort === 'total_paid') {
            $studentsQuery->orderBy('total_paid', $direction);
        } elseif ($sort === 'balance') {
            // Balance = Charged - Credits - Paid
            // Using DB::raw because we are sorting by a calculated expression using aliases
            $studentsQuery->orderByRaw("(total_charged - total_credits - total_paid) $direction");
        } else {
            // Default sort
            $studentsQuery->orderBy('students.full_name', 'asc');
        }

        $students = $studentsQuery->paginate($perPage);

        $invoicesByStudent = StudentInvoice::query()
            ->select(['id', 'student_id', 'invoice_number', 'status', 'due_date', 'created_at'])
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $students->getCollection()->pluck('id'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id');

        return $students->through(function ($student) use ($semesterId, $invoicesByStudent) {
            $totalCharged = (float) $student->total_charged;
            $totalCredits = (float) $student->total_credits;
            $totalPaid = (float) $student->total_paid;

            // Net Due = Charged - Credits (if we consider credit reduces due).
            // However, usually Balance = Net Due - Paid.
            // In my Stats Logic: Balance = Charged - Credits - Paid.
            $balance = $totalCharged - $totalCredits - $totalPaid;
            $amountDue = max($balance, 0);

            // Unapplied Credit = Total Payments - Total Allocations (Anytime)
            $unappliedCredit = (float) $student->total_payments - (float) $student->unapplied_allocations;

            $studentInvoices = $invoicesByStudent->get($student->id, collect());
            /** @var StudentInvoice|null $latestInvoice */
            $latestInvoice = $studentInvoices->first();

            // Get flags
            $flags = $this->getStudentBillingFlags($student->id, $semesterId);
            // Additional Flag: Uncharged
            if ($studentInvoices->isEmpty() && $totalCharged == 0.0) {
                $flags['uncharged'] = true;
            }

            // Stage Logic
            $stage = 'Unknown';
            if ($student->intake_gc) {
                $stage = 'EGC';
            }
            if ($student->intake_course) {
                $stage = 'Major';
            } // Simple logic for now

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'program_code' => $student->program_code,
                'intake_semester' => $student->intakeSemester?->name,
                'stage' => $stage,
                'gc_current_level' => $student->gc_current_level,
                'status' => $student->status,

                'total_charged' => $totalCharged,
                'total_credits' => $totalCredits,
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'amount_due' => $amountDue,
                'unapplied_credit' => $unappliedCredit > 0 ? $unappliedCredit : 0,

                'breakdown' => [
                    'major' => (float) $student->major_fee,
                    'egc' => (float) $student->egc_fee,
                    'retake' => (float) $student->retake_fee,
                    'credits' => (float) $student->total_credits,
                ],

                'flags' => $flags,
                'invoices' => $studentInvoices->map(fn (StudentInvoice $invoice) => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                ])->values()->all(),
                'invoice_statuses' => $studentInvoices->pluck('status')->unique()->values()->all(),
                'invoice_status' => $latestInvoice?->status ?? null,
                'invoice_number' => $latestInvoice?->invoice_number ?? null,
                'invoice_id' => $latestInvoice?->id ?? null,
                'due_date' => $latestInvoice?->due_date?->toDateString(),
            ];
        });
    }

    private function getStudentBillingFlags(int $studentId, ?int $semesterId): array
    {
        // Defer Check
        $deferCase = DeferCase::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

        // Retake Check
        $hasRetake = DB::table('course_registrations')
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('is_retake', true)
            ->exists();

        return [
            'has_retake' => $hasRetake,
            'is_defer_preserve' => $deferCase?->fee_policy === DeferCase::POLICY_PRESERVE,
            'is_defer_forfeit' => $deferCase?->fee_policy === DeferCase::POLICY_FORFEIT,
            'missing_docs' => $deferCase && $deferCase->fee_policy === DeferCase::POLICY_PRESERVE && ! $deferCase->upload_record_id,
            'uncharged' => false, // Set in main loop
        ];
    }
}
