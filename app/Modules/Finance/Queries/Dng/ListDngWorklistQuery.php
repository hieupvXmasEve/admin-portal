<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query the DNG worklist: students with active, unpaid charges of a given DNG fee type.
 *
 * Balance is computed in a single batched query (no N+1) by joining aggregated
 * payment_applications and discount_allocations subqueries at the invoice_lines level.
 *
 * For fee_type = HL/PTL, also surfaces approved Academic sources that have
 * neither a legacy charge link nor a visible payable yet. The legacy
 * needs_charge_creation flag now means Finance repair/obligation intake is
 * required before DNG can push.
 */
class ListDngWorklistQuery
{
    private const SORTABLE = [
        'student_code' => 'students.student_id',
        'student_name' => 'students.full_name',
        'balance' => 'balance',
        'charge_count' => 'charge_count',
    ];

    /**
     * @return array{
     *     students: LengthAwarePaginator,
     *     filters: array<string, mixed>,
     *     summary: array{total_students: int, total_balance: float, students_with_active_dng: int, students_without_dng: int},
     * }
     */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'dng_fee_type' => 'nullable|string|in:'.implode(',', DngFeeTypeOptions::values()),
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'dng_status' => 'nullable|string|in:all,no_dng,has_active_dng',
            'per_page' => 'nullable|integer|min:1|max:200',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $dngFeeType = $validated['dng_fee_type'] ?? 'HP';
        $search = trim((string) ($validated['search'] ?? ''));
        $campusId = isset($validated['campus_id']) ? (int) $validated['campus_id'] : null;
        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $dngStatus = $validated['dng_status'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? 50);
        $sort = self::SORTABLE[$validated['sort'] ?? 'balance'] ?? self::SORTABLE['balance'];
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $chargeTypes = self::mapFeeTypeToChargeTypes($dngFeeType);

        // ------------------------------------------------------------------
        // Batch-aggregate charges per student using subqueries for balance.
        // Avoids N+1 by computing paid/discount at the DB level.
        // ------------------------------------------------------------------
        $campusFilter = app()->bound('campus') ? app('campus')->id : $campusId;

        // Subquery: paid amount per charge (sum of payment_applications via invoice_lines)
        $paidSubquery = DB::table('invoice_lines as il_p')
            ->join('payment_applications as pa', 'pa.invoice_line_id', '=', 'il_p.id')
            ->where('il_p.status', 'active')
            ->selectRaw('il_p.charge_id, COALESCE(SUM(pa.amount), 0) as paid_amount')
            ->groupBy('il_p.charge_id');

        // Subquery: discount amount per charge (sum of discount_allocations via invoice_lines)
        $discountSubquery = DB::table('invoice_lines as il_d')
            ->join('discount_allocations as da', 'da.invoice_line_id', '=', 'il_d.id')
            ->where('il_d.status', 'active')
            ->selectRaw('il_d.charge_id, COALESCE(SUM(da.amount), 0) as discount_amount')
            ->groupBy('il_d.charge_id');

        // Main query aggregated per student
        $query = DB::table('finance_charges as fc')
            ->join('students as s', 's.id', '=', 'fc.student_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->leftJoinSub($paidSubquery, 'paid', 'paid.charge_id', '=', 'fc.id')
            ->leftJoinSub($discountSubquery, 'disc', 'disc.charge_id', '=', 'fc.id')
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->where('fc.amount', '>', 0)
            ->whereIn('fc.charge_type', $chargeTypes)
            ->selectRaw(
                's.id as student_id,
                s.student_id as student_code,
                s.full_name as student_name,
                c.id as campus_id,
                c.name as campus_name,
                COUNT(fc.id) as charge_count,
                SUM(fc.amount) as total_amount,
                COALESCE(SUM(COALESCE(paid.paid_amount, 0)), 0) as total_paid,
                COALESCE(SUM(COALESCE(disc.discount_amount, 0)), 0) as total_discount,
                GREATEST(0,
                    SUM(fc.amount)
                    - COALESCE(SUM(COALESCE(paid.paid_amount, 0)), 0)
                    - COALESCE(SUM(COALESCE(disc.discount_amount, 0)), 0)
                ) as balance'
            )
            ->groupBy('s.id', 's.student_id', 's.full_name', 'c.id', 'c.name')
            ->havingRaw('balance > 0');

        if ($campusFilter !== null) {
            $query->where('s.campus_id', $campusFilter);
        }

        if ($semesterId !== null) {
            $query->where('fc.semester_id', $semesterId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('s.student_id', 'like', "%{$search}%")
                    ->orWhere('s.full_name', 'like', "%{$search}%");
            });
        }

        // Fetch all student rows for summary + DNG status join
        $allStudentRows = (clone $query)->get();
        if ($dngFeeType === 'HL') {
            $allStudentRows = $this->mergeApprovedSourcesWithoutCharge(
                $allStudentRows,
                $this->loadApprovedRetakeSourceRows($campusFilter, $semesterId, $search),
            );
        } elseif ($dngFeeType === 'PTL') {
            $allStudentRows = $this->mergeApprovedSourcesWithoutCharge(
                $allStudentRows,
                $this->loadApprovedExamResitSourceRows($campusFilter, $semesterId, $search),
            );
        }

        $studentIds = $allStudentRows->pluck('student_id')->unique()->values()->all();

        // Active DNG per student for this fee_type
        $activeDngByStudent = DngPaymentRequest::query()
            ->whereIn('student_id', $studentIds)
            ->where('fee_type', $dngFeeType)
            ->awaitingPayment()
            ->orderByDesc('created_at')
            ->get(['id', 'student_id', 'status', 'amount', 'created_at'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->first());

        // Installment-aware metadata per (student, charge):
        //   - next_no: lowest installment_no still pending (what would push next)
        //   - pending_count: installments still pending for this charge
        //   - total_count: total installments planned for this charge (>1 means "split")
        $installmentStatsRows = \DB::table('finance_charge_installments as fci')
            ->join('finance_charges as fc', 'fc.id', '=', 'fci.finance_charge_id')
            ->whereIn('fc.student_id', $studentIds)
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->whereIn('fc.charge_type', $chargeTypes)
            ->when($semesterId !== null, fn ($q) => $q->where('fc.semester_id', $semesterId))
            ->selectRaw("
                fc.student_id,
                fci.finance_charge_id,
                COUNT(fci.id) as total_count,
                SUM(CASE WHEN fci.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                MIN(CASE WHEN fci.status = 'pending' THEN fci.installment_no END) as next_no
            ")
            ->groupBy('fc.student_id', 'fci.finance_charge_id')
            ->get();

        // Resolve next-pending installment row (amount) per (charge).
        $nextInstallmentRowIds = [];
        foreach ($installmentStatsRows as $r) {
            if ($r->next_no === null) {
                continue;
            }
            $nextInstallmentRowIds[] = \DB::table('finance_charge_installments')
                ->where('finance_charge_id', $r->finance_charge_id)
                ->where('installment_no', $r->next_no)
                ->value('id');
        }
        $nextInstallmentRowIds = array_filter($nextInstallmentRowIds);

        $nextInstallmentRows = FinanceChargeInstallment::query()
            ->whereIn('id', $nextInstallmentRowIds)
            ->get(['id', 'finance_charge_id', 'installment_no', 'amount', 'due_date'])
            ->keyBy('finance_charge_id');

        // Aggregate per student.
        $byStudent = $installmentStatsRows->groupBy('student_id');
        $nextPushAmountByStudent = [];
        $pendingCountByStudent = [];
        $hasSplitPlanByStudent = [];
        foreach ($byStudent as $studentId => $chargeRows) {
            $sum = 0.0;
            $totalPending = 0;
            $hasSplit = false;
            foreach ($chargeRows as $r) {
                $inst = $nextInstallmentRows->get($r->finance_charge_id);
                if ($inst !== null) {
                    $sum += (float) $inst->amount;
                }
                $totalPending += (int) $r->pending_count;
                if ((int) $r->total_count > 1) {
                    $hasSplit = true;
                }
            }
            $nextPushAmountByStudent[$studentId] = $sum;
            $pendingCountByStudent[$studentId] = $totalPending;
            $hasSplitPlanByStudent[$studentId] = $hasSplit;
        }

        // Attach DNG status to rows and filter
        $rows = $allStudentRows
            ->map(function ($row) use ($activeDngByStudent, $nextPushAmountByStudent, $pendingCountByStudent, $hasSplitPlanByStudent) {
                $activeDng = $activeDngByStudent->get($row->student_id);
                $row->active_dng = $activeDng ? [
                    'id' => $activeDng->id,
                    'status' => $activeDng->status,
                    'amount' => (float) $activeDng->amount,
                    'created_at' => $activeDng->created_at?->toIso8601String(),
                ] : null;

                // Installment-aware next-push amount. Fall back to balance if no installments exist.
                $row->next_push_amount = isset($nextPushAmountByStudent[$row->student_id])
                    && $nextPushAmountByStudent[$row->student_id] > 0
                    ? $nextPushAmountByStudent[$row->student_id]
                    : (float) $row->balance;
                $row->pending_installment_count = $pendingCountByStudent[$row->student_id] ?? 0;
                // True only when at least one charge has >1 installment planned (real split).
                // Backfilled 1-installment charges of un-split students return false here.
                $row->has_split_plan = $hasSplitPlanByStudent[$row->student_id] ?? false;

                return $row;
            })
            ->when($dngStatus === 'no_dng', fn ($c) => $c->filter(fn ($r) => $r->active_dng === null))
            ->when($dngStatus === 'has_active_dng', fn ($c) => $c->filter(fn ($r) => $r->active_dng !== null))
            ->values();

        // Summary
        $summary = [
            'total_students' => $rows->count(),
            'total_balance' => (float) $rows->sum('balance'),
            'students_with_active_dng' => $rows->filter(fn ($r) => $r->active_dng !== null)->count(),
            'students_without_dng' => $rows->filter(fn ($r) => $r->active_dng === null)->count(),
        ];

        // Sort
        $rows = $rows->sort(function ($a, $b) use ($sort, $direction) {
            $aVal = match ($sort) {
                'students.student_id' => $a->student_code,
                'students.full_name' => $a->student_name,
                'charge_count' => (int) $a->charge_count,
                default => (float) $a->balance,
            };
            $bVal = match ($sort) {
                'students.student_id' => $b->student_code,
                'students.full_name' => $b->student_name,
                'charge_count' => (int) $b->charge_count,
                default => (float) $b->balance,
            };

            return $direction === 'asc' ? ($aVal <=> $bVal) : ($bVal <=> $aVal);
        })->values();

        // Paginate
        $page = max(1, (int) ($request->input('page', 1)));
        $pagedRows = $rows->forPage($page, $perPage)->values();
        $pagedStudentIds = $pagedRows->pluck('student_id')->all();

        // Attach individual charge breakdown for expandable rows (current page only)
        $chargesByStudent = $this->loadChargeBreakdown($pagedStudentIds, $chargeTypes, $semesterId);

        // For HL/PTL fee_type: approved Academic sources missing Finance obligation/repair.
        $needsChargeByStudent = match ($dngFeeType) {
            'HL' => $this->loadApprovedRetakeRegistrationsWithoutCharge($pagedStudentIds, $semesterId),
            'PTL' => $this->loadApprovedExamResitAttemptsWithoutCharge($pagedStudentIds, $semesterId),
            default => collect(),
        };

        $pagedRows = $pagedRows->map(function ($row) use ($chargesByStudent, $needsChargeByStudent) {
            $row->charges = $chargesByStudent->get($row->student_id, collect())->values()->all();
            $row->needs_charge_creation = $needsChargeByStudent->has($row->student_id);
            $row->pending_registrations = $needsChargeByStudent->get($row->student_id, collect())->values()->all();

            // Cast numeric fields
            $row->total_amount = (float) $row->total_amount;
            $row->total_paid = (float) $row->total_paid;
            $row->total_discount = (float) $row->total_discount;
            $row->balance = (float) $row->balance;
            $row->charge_count = (int) $row->charge_count;

            return (array) $row;
        });

        $paginator = new LengthAwarePaginator(
            $pagedRows->all(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return [
            'students' => $paginator->withQueryString(),
            'filters' => [
                'dng_fee_type' => $dngFeeType,
                'search' => $search,
                'campus_id' => $campusId,
                'semester_id' => $semesterId,
                'dng_status' => $dngStatus,
                'per_page' => $perPage,
                'sort' => array_search($sort, self::SORTABLE, true) ?: 'balance',
                'direction' => $direction,
            ],
            'summary' => $summary,
        ];
    }

    /**
     * Map DNG fee_type to internal charge_type values.
     *
     * @return array<int, string>
     */
    public static function mapFeeTypeToChargeTypes(string $dngFeeType): array
    {
        return match ($dngFeeType) {
            'HP' => [FinanceCharge::TYPE_TUITION_TERM, FinanceCharge::TYPE_EGC_LEVEL_FEE, FinanceCharge::TYPE_COURSE_FEE],
            'HL' => [FinanceCharge::TYPE_RETAKE_FEE],
            'PTL' => [FinanceCharge::TYPE_EXAM_RESIT_FEE],
            'BHYT' => [FinanceCharge::TYPE_BHYT],
            'KHAC' => [FinanceCharge::TYPE_MANUAL_FEE, FinanceCharge::TYPE_ADJUSTMENT],
            default => throw new \InvalidArgumentException("Unknown DNG fee type: {$dngFeeType}"),
        };
    }

    /**
     * Load individual charge breakdown for expandable rows (current page students only).
     *
     * @param  array<int, int>  $studentIds
     * @param  array<int, string>  $chargeTypes
     * @return Collection<int, Collection> student_id → array of charge rows
     */
    private function loadChargeBreakdown(array $studentIds, array $chargeTypes, ?int $semesterId): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        $paidSubquery = DB::table('invoice_lines as il_p')
            ->join('payment_applications as pa', 'pa.invoice_line_id', '=', 'il_p.id')
            ->where('il_p.status', 'active')
            ->selectRaw('il_p.charge_id, COALESCE(SUM(pa.amount), 0) as paid_amount')
            ->groupBy('il_p.charge_id');

        $discountSubquery = DB::table('invoice_lines as il_d')
            ->join('discount_allocations as da', 'da.invoice_line_id', '=', 'il_d.id')
            ->where('il_d.status', 'active')
            ->selectRaw('il_d.charge_id, COALESCE(SUM(da.amount), 0) as discount_amount')
            ->groupBy('il_d.charge_id');

        $charges = DB::table('finance_charges as fc')
            ->leftJoin('semesters as sem', 'sem.id', '=', 'fc.semester_id')
            ->leftJoinSub($paidSubquery, 'paid', 'paid.charge_id', '=', 'fc.id')
            ->leftJoinSub($discountSubquery, 'disc', 'disc.charge_id', '=', 'fc.id')
            ->whereIn('fc.student_id', $studentIds)
            ->where('fc.status', FinanceCharge::STATUS_ACTIVE)
            ->where('fc.amount', '>', 0)
            ->whereIn('fc.charge_type', $chargeTypes)
            ->when($semesterId, fn ($q) => $q->where('fc.semester_id', $semesterId))
            ->selectRaw(
                'fc.id,
                fc.student_id,
                fc.charge_type,
                fc.amount,
                fc.description,
                fc.semester_id,
                sem.name as semester_name,
                COALESCE(paid.paid_amount, 0) as paid,
                COALESCE(disc.discount_amount, 0) as discount,
                GREATEST(0,
                    fc.amount
                    - COALESCE(paid.paid_amount, 0)
                    - COALESCE(disc.discount_amount, 0)
                ) as balance'
            )
            ->orderBy('fc.created_at')
            ->get();

        return $charges
            ->filter(fn ($c) => (float) $c->balance > 0)
            ->groupBy('student_id')
            ->map(fn ($group) => $group->map(fn ($c) => [
                'id' => $c->id,
                'charge_type' => $c->charge_type,
                'amount' => (float) $c->amount,
                'balance' => (float) $c->balance,
                'paid' => (float) $c->paid,
                'discount' => (float) $c->discount,
                'description' => $c->description,
                'semester' => $c->semester_name,
                'semester_id' => $c->semester_id,
            ]));
    }

    /**
     * For HL fee_type: find approved retake registrations without a charge link.
     * These students need Finance obligation repair before DNG can be pushed.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection> student_id → array of registration rows
     */
    private function loadApprovedRetakeRegistrationsWithoutCharge(array $studentIds, ?int $semesterId): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        $registrations = CourseRetakeRegistration::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->whereNull('finance_charge_id')
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->with(['unit:id,code,name', 'semester:id,name'])
            ->get(['id', 'student_id', 'unit_id', 'semester_id', 'retake_fee', 'status'])
            ->map(fn ($reg) => [
                'id' => $reg->id,
                'student_id' => $reg->student_id,
                'unit_code' => $reg->unit?->code,
                'unit_name' => $reg->unit?->name,
                'semester_name' => $reg->semester?->name,
                'retake_fee' => (float) $reg->retake_fee,
            ]);

        return $registrations->groupBy('student_id');
    }

    private function loadApprovedRetakeSourceRows(?int $campusId, ?int $semesterId, string $search): Collection
    {
        $registrations = CourseRetakeRegistration::query()
            ->with(['student:id,student_id,full_name,campus_id', 'student.campus:id,name', 'campus:id,name'])
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->whereNull('finance_charge_id')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->get();

        return $registrations
            ->groupBy('student_id')
            ->map(function (Collection $studentRegistrations) {
                /** @var CourseRetakeRegistration $first */
                $first = $studentRegistrations->first();
                $student = $first->student;
                $campus = $first->campus ?? $student?->campus;
                $total = (float) $studentRegistrations->sum(fn (CourseRetakeRegistration $registration) => (float) $registration->retake_fee);

                return (object) [
                    'student_id' => $first->student_id,
                    'student_code' => $student?->student_id,
                    'student_name' => $student?->full_name,
                    'campus_id' => $campus?->id,
                    'campus_name' => $campus?->name,
                    'charge_count' => 0,
                    'total_amount' => $total,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'balance' => $total,
                ];
            })
            ->values();
    }

    /**
     * For PTL fee_type: find approved exam-resit attempts without a charge link.
     * These students need Finance obligation repair before DNG can be pushed.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection> student_id → array of attempt rows
     */
    private function loadApprovedExamResitAttemptsWithoutCharge(array $studentIds, ?int $semesterId): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        $attempts = ExamResitAttempt::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', ExamResitAttempt::STATUS_APPROVED)
            ->where('hq_fee_status', ExamResitAttempt::HQ_FEE_PENDING)
            ->whereNull('finance_charge_id')
            ->when($semesterId !== null, fn ($query) => $query->where('charge_semester_id', $semesterId))
            ->with(['unit:id,code,name', 'chargeSemester:id,name'])
            ->get(['id', 'student_id', 'unit_id', 'charge_semester_id', 'fee_amount', 'status', 'hq_fee_status'])
            ->map(fn ($attempt) => [
                'id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'unit_code' => $attempt->unit?->code,
                'unit_name' => $attempt->unit?->name,
                'semester_name' => $attempt->chargeSemester?->name,
                'retake_fee' => (float) $attempt->fee_amount,
            ]);

        return $attempts->groupBy('student_id');
    }

    private function loadApprovedExamResitSourceRows(?int $campusId, ?int $semesterId, string $search): Collection
    {
        $attempts = ExamResitAttempt::query()
            ->with(['student:id,student_id,full_name,campus_id', 'student.campus:id,name', 'campus:id,name'])
            ->where('status', ExamResitAttempt::STATUS_APPROVED)
            ->where('hq_fee_status', ExamResitAttempt::HQ_FEE_PENDING)
            ->whereNull('finance_charge_id')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn ($query) => $query->where('charge_semester_id', $semesterId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->get();

        return $attempts
            ->groupBy('student_id')
            ->map(function (Collection $studentAttempts) {
                /** @var ExamResitAttempt $first */
                $first = $studentAttempts->first();
                $student = $first->student;
                $campus = $first->campus ?? $student?->campus;
                $total = (float) $studentAttempts->sum(fn (ExamResitAttempt $attempt) => (float) $attempt->fee_amount);

                return (object) [
                    'student_id' => $first->student_id,
                    'student_code' => $student?->student_id,
                    'student_name' => $student?->full_name,
                    'campus_id' => $campus?->id,
                    'campus_name' => $campus?->name,
                    'charge_count' => 0,
                    'total_amount' => $total,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'balance' => $total,
                ];
            })
            ->values();
    }

    private function mergeApprovedSourcesWithoutCharge(Collection $chargeRows, Collection $sourceRows): Collection
    {
        $merged = $chargeRows->keyBy('student_id');

        foreach ($sourceRows as $sourceRow) {
            $existing = $merged->get($sourceRow->student_id);

            if ($existing === null) {
                $merged->put($sourceRow->student_id, $sourceRow);

                continue;
            }

            $existing->total_amount = (float) $existing->total_amount + (float) $sourceRow->total_amount;
            $existing->balance = (float) $existing->balance + (float) $sourceRow->balance;
        }

        return $merged->values();
    }
}
