<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
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
 * For fee_type = HL, also surfaces CourseRetakeRegistration records in 'approved' status
 * that do not yet have a linked finance_charge (needs_charge_creation = true rows).
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

        // Attach DNG status to rows and filter
        $rows = $allStudentRows
            ->map(function ($row) use ($activeDngByStudent) {
                $activeDng = $activeDngByStudent->get($row->student_id);
                $row->active_dng = $activeDng ? [
                    'id' => $activeDng->id,
                    'status' => $activeDng->status,
                    'amount' => (float) $activeDng->amount,
                    'created_at' => $activeDng->created_at?->toIso8601String(),
                ] : null;

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

        // For HL fee_type: approved registrations without charges (needs_charge_creation)
        $needsChargeByStudent = $dngFeeType === 'HL'
            ? $this->loadApprovedRetakeRegistrationsWithoutCharge($pagedStudentIds)
            : collect();

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
     * For HL fee_type: find approved retake registrations without a charge yet.
     * These students need charge creation before DNG can be pushed.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Collection> student_id → array of registration rows
     */
    private function loadApprovedRetakeRegistrationsWithoutCharge(array $studentIds): Collection
    {
        if (empty($studentIds)) {
            return collect();
        }

        $registrations = CourseRetakeRegistration::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->whereNull('finance_charge_id')
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
}
