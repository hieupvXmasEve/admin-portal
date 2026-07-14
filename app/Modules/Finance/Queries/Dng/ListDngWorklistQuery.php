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
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Query the DNG worklist: students with active, unpaid charges of a given DNG fee type.
 *
 * Balance comes from grouped, batched Settlement Position reads (no N+1).
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

    public function __construct(
        private readonly SettlementPositionWorklistReader $positionReader,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
    ) {}

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

        $campusFilter = app()->bound('campus') ? app('campus')->id : $campusId;

        $lines = InvoiceLine::query()
            ->with(['invoice.student.campus', 'invoice.semester', 'charge.financeObligation'])
            ->where('invoice_lines.status', 'active')
            ->whereHas('charge', function ($query) use ($chargeTypes): void {
                $query->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->where('amount', '>', 0)
                    ->whereIn('charge_type', $chargeTypes);
            })
            ->whereHas('invoice', function ($query) use ($campusFilter, $semesterId, $search): void {
                $query->when($semesterId !== null, fn ($q) => $q->where('semester_id', $semesterId))
                    ->when($campusFilter !== null, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusFilter)))
                    ->when($search !== '', fn ($q) => $q->whereHas('student', function ($sq) use ($search): void {
                        $sq->where('student_id', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%");
                    }));
            })
            ->get();

        $lineIdsByStudent = $lines->groupBy(fn (InvoiceLine $line): int => (int) $line->invoice->student_id)
            ->map(fn (Collection $studentLines): array => $studentLines->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->all();
        $positionsByStudent = $this->positionReader->forLineGroups($lineIdsByStudent);

        $allStudentRows = $lines->groupBy(fn (InvoiceLine $line): int => (int) $line->invoice->student_id)
            ->map(function (Collection $studentLines, int|string $studentId) use ($positionsByStudent): object {
                $studentId = (int) $studentId;
                $firstLine = $studentLines->first();
                $student = $firstLine->invoice->student;
                $position = $positionsByStudent[$studentId] ?? null;
                $summary = $position instanceof SettlementPosition
                    ? $this->positionPresenter->summarize($position)
                    : $this->missingSummary();

                return (object) [
                    'student_id' => $studentId,
                    'student_code' => $student?->student_id,
                    'student_name' => $student?->full_name,
                    'campus_id' => $student?->campus_id,
                    'campus_name' => $student?->campus?->name,
                    'charge_count' => $studentLines->pluck('charge_id')->filter()->unique()->count(),
                    'total_amount' => $summary['net'],
                    'total_paid' => $summary['cash'],
                    'total_discount' => $summary['discount'],
                    'balance' => $summary['remaining'],
                    'settlement_state' => $summary['settlement_state'],
                    'settlement_label' => $summary['settlement_label'],
                    'settlement_issues' => $summary['issues'],
                    'valid' => $summary['valid'],
                    'needs_review' => ! $summary['valid'],
                ];
            })
            ->filter(fn (object $row): bool => $row->needs_review || ($row->balance !== null && $row->balance > 0))
            ->values();

        $unlinkedCharges = FinanceCharge::query()
            ->with(['student.campus'])
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->whereIn('charge_type', $chargeTypes)
            ->whereDoesntHave('invoiceLines', fn ($query) => $query->where('status', 'active'))
            ->when($campusFilter !== null, fn ($query) => $query->whereHas('student', fn ($q) => $q->where('campus_id', $campusFilter)))
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->when($search !== '', fn ($query) => $query->whereHas('student', function ($q) use ($search): void {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            }))
            ->get();

        foreach ($unlinkedCharges->groupBy('student_id') as $studentId => $charges) {
            $firstCharge = $charges->first();
            $student = $firstCharge->student;
            $missingRow = (object) [
                'student_id' => (int) $studentId,
                'student_code' => $student?->student_id,
                'student_name' => $student?->full_name,
                'campus_id' => $student?->campus_id,
                'campus_name' => $student?->campus?->name,
                'charge_count' => $charges->count(),
                'total_amount' => null,
                'total_paid' => null,
                'total_discount' => null,
                'balance' => null,
                'settlement_state' => SettlementPosition::STATE_MISSING,
                'settlement_label' => 'Cần kiểm tra',
                'settlement_issues' => [[
                    'code' => 'settlement_position.missing_payable_line',
                    'severity' => 'blocking',
                    'blocking' => true,
                    'evidence' => ['charge_count' => $charges->count()],
                    'finance_invariant_code' => null,
                ]],
                'valid' => false,
                'needs_review' => true,
            ];
            $existing = $allStudentRows->firstWhere('student_id', (int) $studentId);

            if ($existing !== null) {
                $allStudentRows = $allStudentRows->reject(fn (object $row): bool => $row->student_id === (int) $studentId)->push($missingRow);
            } else {
                $allStudentRows = $allStudentRows->push($missingRow);
            }
        }

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

        $exceptions = $allStudentRows->filter(fn (object $row): bool => $row->needs_review)->values();
        $allStudentRows = $allStudentRows
            ->filter(fn (object $row): bool => $row->valid && $row->balance !== null && $row->balance > 0)
            ->values();

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

        $nextInstallmentRows = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $installmentStatsRows->pluck('finance_charge_id')->all())
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->orderBy('installment_no')
            ->get(['id', 'finance_charge_id', 'installment_no', 'amount', 'due_date'])
            ->groupBy('finance_charge_id')
            ->map(fn (Collection $rows) => $rows->first());

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

        // Attach DNG status to rows and keep invalid positions out of push actions.
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
                $row->next_push_amount = $this->positionPresenter->capCollectionAmount(
                    $row->valid ? $row->balance : null,
                    $nextPushAmountByStudent[$row->student_id] ?? null,
                );
                $row->pending_installment_count = $pendingCountByStudent[$row->student_id] ?? 0;
                // True only when at least one charge has >1 installment planned (real split).
                // Backfilled 1-installment charges of un-split students return false here.
                $row->has_split_plan = $hasSplitPlanByStudent[$row->student_id] ?? false;

                return $row;
            })
            ->when($dngStatus === 'no_dng', fn ($c) => $c->filter(fn ($r) => $r->active_dng === null))
            ->when($dngStatus === 'has_active_dng', fn ($c) => $c->filter(fn ($r) => $r->active_dng !== null))
            ->values();

        // Summary uses only valid canonical positions; invalid scopes are review work.
        $summary = [
            'total_students' => $rows->count(),
            'total_balance' => (float) $rows->where('valid', true)->sum('balance'),
            'students_with_active_dng' => $rows->filter(fn ($r) => $r->active_dng !== null)->count(),
            'students_without_dng' => $rows->filter(fn ($r) => $r->active_dng === null)->count(),
            'needs_review_students' => $exceptions->count(),
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
            $row->total_amount = $row->total_amount === null ? null : (float) $row->total_amount;
            $row->total_paid = $row->total_paid === null ? null : (float) $row->total_paid;
            $row->total_discount = $row->total_discount === null ? null : (float) $row->total_discount;
            $row->balance = $row->balance === null ? null : (float) $row->balance;
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
            'exceptions' => $exceptions->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    /**
     * Map DNG fee_type to internal charge_type values.
     *
     * Derived from ObligationTypeRegistry so reverse mapping cannot drift from
     * charge_type → DNG collection codes (ADR-0027).
     *
     * @return array<int, string>
     */
    public static function mapFeeTypeToChargeTypes(string $dngFeeType): array
    {
        $types = ObligationTypeRegistry::chargeTypesForDngCollectionCode($dngFeeType);

        if ($types === []) {
            throw new \InvalidArgumentException("Unknown DNG fee type: {$dngFeeType}");
        }

        return $types;
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

        $lines = InvoiceLine::query()
            ->with(['invoice.semester', 'charge.financeObligation'])
            ->where('status', 'active')
            ->whereIn('invoice_id', function ($query) use ($studentIds): void {
                $query->select('id')->from('student_invoices')->whereIn('student_id', $studentIds);
            })
            ->whereHas('charge', function ($query) use ($chargeTypes, $semesterId): void {
                $query->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->where('amount', '>', 0)
                    ->whereIn('charge_type', $chargeTypes)
                    ->when($semesterId !== null, fn ($q) => $q->where('semester_id', $semesterId));
            })
            ->get();

        $lineIdsByLine = $lines->mapWithKeys(fn (InvoiceLine $line): array => [(string) $line->id => [(int) $line->id]])->all();
        $positions = $this->positionReader->forLineGroups($lineIdsByLine);

        return $lines
            ->map(function (InvoiceLine $line) use ($positions): ?array {
                $position = $positions[(string) $line->id] ?? null;
                $summary = $position instanceof SettlementPosition
                    ? $this->positionPresenter->summarize($position)
                    : $this->missingSummary();

                if (! $summary['valid'] || $summary['remaining'] === null || $summary['remaining'] <= 0) {
                    return null;
                }

                return [
                    'id' => $line->charge?->id,
                    'charge_type' => $line->charge?->charge_type,
                    'amount' => $summary['gross'],
                    'balance' => $summary['remaining'],
                    'paid' => $summary['cash'],
                    'discount' => $summary['discount'],
                    'description' => $line->description_snapshot ?: $line->charge?->description,
                    'semester' => $line->invoice->semester?->name,
                    'semester_id' => $line->invoice->semester_id,
                    'settlement_state' => $summary['settlement_state'],
                    'settlement_label' => $summary['settlement_label'],
                ];
            })
            ->filter()
            ->groupBy(fn (array $charge): int => (int) $lines->firstWhere('charge_id', $charge['id'])?->invoice?->student_id)
            ->map(fn (Collection $group): Collection => $group->values());
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
                    'total_amount' => null,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'balance' => null,
                    'settlement_state' => SettlementPosition::STATE_MISSING,
                    'settlement_label' => 'Cần kiểm tra',
                    'settlement_issues' => [[
                        'code' => 'settlement_position.missing_payable_line',
                        'severity' => 'blocking',
                        'blocking' => true,
                        'evidence' => [],
                        'finance_invariant_code' => null,
                    ]],
                    'valid' => false,
                    'needs_review' => true,
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
                    'total_amount' => null,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'balance' => null,
                    'settlement_state' => SettlementPosition::STATE_MISSING,
                    'settlement_label' => 'Cần kiểm tra',
                    'settlement_issues' => [[
                        'code' => 'settlement_position.missing_payable_line',
                        'severity' => 'blocking',
                        'blocking' => true,
                        'evidence' => [],
                        'finance_invariant_code' => null,
                    ]],
                    'valid' => false,
                    'needs_review' => true,
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

            $existing->total_amount = null;
            $existing->balance = null;
            $existing->total_paid = null;
            $existing->total_discount = null;
            $existing->valid = false;
            $existing->needs_review = true;
            $existing->settlement_state = SettlementPosition::STATE_INVALID;
            $existing->settlement_label = 'Cần kiểm tra';
            $existing->settlement_issues = [[
                'code' => 'settlement_position.missing_payable_line',
                'severity' => 'blocking',
                'blocking' => true,
                'evidence' => [],
                'finance_invariant_code' => null,
            ]];
        }

        return $merged->values();
    }
}
