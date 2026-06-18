<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Models\FinanceCharge;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Support\Reporting\FeeMonitorExpectedFeeCatalog;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListFeeMonitorQuery
{
    /** @var array<string, FinanceCharge> */
    private array $chargeIndex = [];

    /** @var array<int, Student> */
    private array $studentIndex = [];

    /** @var array<int, float> */
    private array $paidAmountIndex = [];

    public function __construct(
        private readonly PreviewMajorChargeGenerationQuery $majorPreviewQuery,
        private readonly PreviewEgcChargeGenerationQuery $egcPreviewQuery,
        private readonly StudentChargeTimingResolver $timingResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator, summary: array<string, int>}
     */
    public function handle(int $semesterId, array $filters = []): array
    {
        $rows = $this->hydratePaymentStates($this->collectRows($semesterId, $filters));
        $rows = $this->applyPaymentStateFilter($rows, $filters);
        $perPage = in_array((int) ($filters['per_page'] ?? 20), [20, 50, 100], true)
            ? (int) $filters['per_page']
            : 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageRows = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'rows' => $paginator,
            'summary' => app(GetFeeMonitorSummaryQuery::class)->fromRows($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectRows(int $semesterId, array $filters = []): Collection
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $this->primeChargeIndex($semesterId, $campusId);

        $rows = collect();
        $rows = $rows->merge($this->rowsFromTuitionPreview($semesterId, $campusId, $filters));
        $rows = $rows->merge($this->rowsFromEgcPreview($semesterId, $campusId, $filters));
        $rows = $rows->merge($this->rowsFromAdmissionExpectation($semesterId, $campusId, $filters));
        $rows = $rows->merge($this->rowsFromBhytExpectation($semesterId, $campusId, $filters));
        $rows = $rows->merge($this->rowsFromExistingSourceCharges($semesterId, $campusId, $filters));

        return $this->applyRowFilters($rows, $filters)->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filterOptions(int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $programs = Program::query()
            ->select(['programs.id', 'programs.code', 'programs.name'])
            ->whereIn('programs.id', function ($subquery) use ($campusId): void {
                $subquery->select('program_id')
                    ->from('students')
                    ->whereNotNull('program_id')
                    ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
                    ->distinct();
            })
            ->orderBy('code')
            ->get()
            ->map(fn (Program $program) => [
                'value' => $program->id,
                'label' => $program->code.' — '.$program->name,
            ])
            ->values()
            ->all();

        $intakes = Student::query()
            ->select('intake_semester_id')
            ->whereNotNull('intake_semester_id')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->distinct()
            ->pluck('intake_semester_id')
            ->filter()
            ->values();

        $intakeSemesters = Semester::query()
            ->whereIn('id', $intakes)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (Semester $semester) => [
                'value' => $semester->id,
                'label' => $semester->name,
            ])
            ->values()
            ->all();

        $cohorts = Student::query()
            ->select('intake')
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->distinct()
            ->orderBy('intake')
            ->pluck('intake')
            ->filter(fn ($intake) => $intake !== null)
            ->map(fn (int $intake) => [
                'value' => $intake,
                'label' => 'Intake '.$intake,
            ])
            ->values()
            ->all();

        return [
            'programs' => $programs,
            'intakes' => $intakeSemesters,
            'cohorts' => $cohorts,
            'expected_fee_types' => collect(FeeMonitorExpectedFeeCatalog::sources())
                ->map(fn (array $meta, string $source) => [
                    'value' => $source,
                    'label' => $meta['label'],
                    'charge_type' => $meta['charge_type'],
                ])
                ->values()
                ->all(),
            'generation_states' => [
                ['value' => 'missing', 'label' => 'Thiếu phí'],
                ['value' => 'generated', 'label' => 'Đã sinh phí'],
                ['value' => 'blocked', 'label' => 'Bị chặn'],
                ['value' => 'voided', 'label' => 'Đã void'],
                ['value' => 'skipped', 'label' => 'Bỏ qua'],
            ],
            'student_statuses' => collect(Student::FINANCIAL_STATUSES)
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => self::statusLabelFor($status),
                ])
                ->values()
                ->all(),
            'semester_id' => $semesterId,
        ];
    }

    private function primeChargeIndex(int $semesterId, ?int $campusId): void
    {
        $this->chargeIndex = [];
        $this->studentIndex = [];
        $this->paidAmountIndex = [];

        $charges = FinanceCharge::query()
            ->with(['student.program'])
            ->where('semester_id', $semesterId)
            ->when($campusId !== null, fn ($query) => $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->get();

        foreach ($charges as $charge) {
            $key = $this->chargeKey((int) $charge->student_id, (string) $charge->charge_type);
            $existing = $this->chargeIndex[$key] ?? null;

            if ($existing === null || $this->chargePrecedence($charge) < $this->chargePrecedence($existing)) {
                $this->chargeIndex[$key] = $charge;
            }

            if ($charge->student !== null) {
                $this->studentIndex[(int) $charge->student_id] = $charge->student;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromTuitionPreview(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $scopedFilters = $this->previewFilters($semesterId, $campusId, $filters, 'tuition');
        $classifications = $this->majorPreviewQuery->collectClassificationRows($semesterId, $scopedFilters, $campusId);

        return $this->rowsFromClassifications(
            $classifications,
            FeeMonitorExpectedFeeCatalog::SOURCE_TUITION_PLAN,
            FinanceCharge::TYPE_TUITION_TERM,
            $semesterId,
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromEgcPreview(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $scopedFilters = $this->previewFilters($semesterId, $campusId, $filters, 'egc');
        $classifications = $this->egcPreviewQuery->collectClassificationRows($semesterId, $scopedFilters, $campusId);

        return $this->rowsFromClassifications(
            $classifications,
            FeeMonitorExpectedFeeCatalog::SOURCE_EGC_TUITION,
            FinanceCharge::TYPE_EGC_LEVEL_FEE,
            $semesterId,
            $filters,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $classifications
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromClassifications(
        Collection $classifications,
        string $source,
        string $chargeType,
        int $semesterId,
        array $filters,
    ): Collection {
        $includeSkipped = $this->shouldIncludeSkippedRows($filters);
        $rows = collect();

        foreach ($classifications as $item) {
            $eligibilityStatus = (string) ($item['eligibility_status'] ?? 'ineligible');
            $studentId = (int) $item['student_id'];
            $charge = $this->resolveChargeForStudent($studentId, $chargeType);

            if ($eligibilityStatus === 'ineligible' && ! $includeSkipped && $charge === null) {
                continue;
            }

            $student = $this->resolveStudent($studentId);
            if ($student === null) {
                continue;
            }
            $generationState = $this->mapPreviewEligibilityToGenerationState($item, $charge);

            $rows->push($this->buildExpectationRow(
                $student,
                $source,
                $chargeType,
                $semesterId,
                $charge,
                $generationState,
                (string) ($item['eligibility_reason'] ?? ''),
            ));
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromAdmissionExpectation(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $students = $this->baseStudentQuery($campusId, $filters)
            ->where('intake_semester_id', $semesterId)
            ->whereIn('status', Student::FINANCIAL_STATUSES)
            ->get();

        return $students->map(function (Student $student) use ($semesterId): array {
            $this->studentIndex[$student->id] = $student;
            $charge = $this->resolveChargeForStudent($student->id, FinanceCharge::TYPE_ADMISSION_FEE);

            return $this->buildExpectationRow(
                $student,
                FeeMonitorExpectedFeeCatalog::SOURCE_ADMISSION_ENROLLMENT,
                FinanceCharge::TYPE_ADMISSION_FEE,
                $semesterId,
                $charge,
                null,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromBhytExpectation(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $students = $this->baseStudentQuery($campusId, $filters)
            ->whereIn('status', Student::FINANCIAL_STATUSES)
            ->where(function ($query) use ($semesterId): void {
                $query
                    ->whereHas('courseRegistrations', fn ($registrationQuery) => $registrationQuery
                        ->where('semester_id', $semesterId)
                        ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn']))
                    ->orWhereHas('financeCharges', fn ($chargeQuery) => $chargeQuery
                        ->where('semester_id', $semesterId)
                        ->where('charge_type', FinanceCharge::TYPE_BHYT));
            })
            ->get()
            ->filter(fn (Student $student): bool => $this->timingResolver->shouldIncludeStudentForChargeGeneration(
                $student,
                $semesterId,
                [FinanceCharge::TYPE_BHYT],
            ));

        return $students->map(function (Student $student) use ($semesterId): array {
            $this->studentIndex[$student->id] = $student;
            $charge = $this->resolveChargeForStudent($student->id, FinanceCharge::TYPE_BHYT);

            return $this->buildExpectationRow(
                $student,
                FeeMonitorExpectedFeeCatalog::SOURCE_BHYT,
                FinanceCharge::TYPE_BHYT,
                $semesterId,
                $charge,
                null,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromExistingSourceCharges(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $chargeTypes = [
            FinanceCharge::TYPE_RETAKE_FEE,
            FinanceCharge::TYPE_EXAM_RESIT_FEE,
        ];

        $rows = collect();

        foreach ($this->chargeIndex as $charge) {
            if (! in_array($charge->charge_type, $chargeTypes, true) || $charge->student === null) {
                continue;
            }

            $source = match ($charge->charge_type) {
                FinanceCharge::TYPE_RETAKE_FEE => FeeMonitorExpectedFeeCatalog::SOURCE_COURSE_RETAKE,
                FinanceCharge::TYPE_EXAM_RESIT_FEE => FeeMonitorExpectedFeeCatalog::SOURCE_EXAM_RESIT,
                default => null,
            };

            $row = $this->buildExpectationRow(
                $charge->student,
                (string) $source,
                $charge->charge_type,
                $semesterId,
                $charge,
                $charge->status === FinanceCharge::STATUS_VOID ? 'voided' : 'generated',
            );

            if ($this->matchesStudentScopedFilters($row, $filters)) {
                $rows->push($row);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $previewRow
     */
    private function mapPreviewEligibilityToGenerationState(array $previewRow, ?FinanceCharge $charge): string
    {
        if ($charge?->status === FinanceCharge::STATUS_ACTIVE) {
            return 'generated';
        }

        if ($charge?->status === FinanceCharge::STATUS_VOID) {
            return 'voided';
        }

        return match ($previewRow['eligibility_status'] ?? null) {
            'eligible' => 'missing',
            'warning' => 'blocked',
            default => 'skipped',
        };
    }

    private function buildExpectationRow(
        Student $student,
        string $source,
        string $chargeType,
        int $semesterId,
        ?FinanceCharge $charge,
        ?string $generationState,
        string $reasonCode = '',
    ): array {
        $generationState ??= $this->resolveGenerationStateFromCharge($charge);
        $amount = $charge !== null ? (float) $charge->amount : null;

        return [
            'row_key' => $student->id.'-'.$source.'-'.$semesterId,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'status_label' => $student->status_label,
            ],
            'program_code' => $student->program?->code,
            'intake_semester_id' => $student->intake_semester_id,
            'cohort' => $student->intake,
            'expected_source' => $source,
            'expected_fee_type' => $chargeType,
            'expected_fee_type_label' => FeeMonitorExpectedFeeCatalog::sources()[$source]['label'] ?? $chargeType,
            'semester_id' => $semesterId,
            'generation_state' => $generationState,
            'generation_reason' => $reasonCode !== '' ? $reasonCode : null,
            'payment_state' => null,
            'amount' => $amount,
            'paid_amount' => 0.0,
            'outstanding_amount' => $amount,
            'finance_charge_id' => $charge?->id,
            'source_type' => $charge?->source_type,
            'source_id' => $charge?->source_id,
            'batch_handoff' => $this->resolveBatchHandoff($source, $generationState),
            'drilldowns' => [
                'student_360_focus' => $charge !== null ? 'charge:'.$charge->id : null,
                'lookup_charge_id' => $charge?->id,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function hydratePaymentStates(Collection $rows): Collection
    {
        $chargeIds = $rows
            ->filter(fn (array $row) => $row['generation_state'] === 'generated' && $row['finance_charge_id'] !== null)
            ->pluck('finance_charge_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($chargeIds === []) {
            return $rows;
        }

        $paidByChargeId = PaymentApplication::query()
            ->selectRaw('invoice_lines.charge_id as charge_id, SUM(payment_applications.amount) as paid_amount')
            ->join('invoice_lines', 'invoice_lines.id', '=', 'payment_applications.invoice_line_id')
            ->whereIn('invoice_lines.charge_id', $chargeIds)
            ->groupBy('invoice_lines.charge_id')
            ->pluck('paid_amount', 'charge_id');

        return $rows->map(function (array $row) use ($paidByChargeId): array {
            $chargeId = $row['finance_charge_id'];
            if ($chargeId === null || $row['generation_state'] !== 'generated') {
                return $row;
            }

            $paidAmount = max(0.0, (float) ($paidByChargeId[$chargeId] ?? 0.0));
            $amount = $row['amount'] !== null ? (float) $row['amount'] : null;
            $row['paid_amount'] = $paidAmount;
            $row['payment_state'] = $this->resolvePaymentState('generated', $amount, $paidAmount);
            $row['outstanding_amount'] = $amount !== null ? max(0.0, $amount - $paidAmount) : null;

            return $row;
        });
    }

    private function resolveGenerationStateFromCharge(?FinanceCharge $charge): string
    {
        if ($charge === null) {
            return 'missing';
        }

        return $charge->status === FinanceCharge::STATUS_VOID ? 'voided' : 'generated';
    }

    private function resolvePaymentState(string $generationState, ?float $amount, float $paidAmount): ?string
    {
        if ($generationState !== 'generated' || $amount === null) {
            return null;
        }

        if ($paidAmount <= 0) {
            return 'outstanding';
        }

        if ($paidAmount + 0.01 >= $amount) {
            return 'paid';
        }

        return 'partially_paid';
    }

    private function resolveBatchHandoff(string $source, string $generationState): ?array
    {
        if ($generationState !== 'missing' && $generationState !== 'blocked') {
            return null;
        }

        return match ($source) {
            FeeMonitorExpectedFeeCatalog::SOURCE_TUITION_PLAN => [
                'label' => 'Batch Studio — HP/Tuition',
                'fee_category' => 'major',
            ],
            FeeMonitorExpectedFeeCatalog::SOURCE_EGC_TUITION => [
                'label' => 'Batch Studio — EGC',
                'fee_category' => 'egc',
            ],
            FeeMonitorExpectedFeeCatalog::SOURCE_BHYT,
            FeeMonitorExpectedFeeCatalog::SOURCE_ADMISSION_ENROLLMENT => [
                'label' => 'Batch Studio — Non-academic',
                'fee_category' => 'non_academic',
                'fee_type' => FeeMonitorExpectedFeeCatalog::chargeTypeForSource($source),
            ],
            default => null,
        };
    }

    private function resolveChargeForStudent(int $studentId, string $chargeType): ?FinanceCharge
    {
        return $this->chargeIndex[$this->chargeKey($studentId, $chargeType)] ?? null;
    }

    private function resolveStudent(int $studentId): ?Student
    {
        if (isset($this->studentIndex[$studentId])) {
            return $this->studentIndex[$studentId];
        }

        $student = Student::query()->with('program')->find($studentId);
        if ($student !== null) {
            $this->studentIndex[$studentId] = $student;
        }

        return $student;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function previewFilters(int $semesterId, ?int $campusId, array $filters, string $lane): array
    {
        return array_merge($this->studentScopedFilters($filters), [
            'student_ids' => $this->scopedCandidateStudentIds($semesterId, $campusId, $filters, $lane)->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scopedCandidateStudentIds(int $semesterId, ?int $campusId, array $filters, string $lane): Collection
    {
        $chargedStudentIds = FinanceCharge::query()
            ->where('semester_id', $semesterId)
            ->where('charge_type', $lane === 'tuition' ? FinanceCharge::TYPE_TUITION_TERM : FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->when($campusId !== null, fn ($query) => $query->whereHas(
                'student',
                fn ($studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->pluck('student_id');

        $query = $this->baseStudentQuery($campusId, $filters)
            ->where('intake_semester_id', '<=', $semesterId);

        if ($lane === 'tuition') {
            $query
                ->whereIn('status', ['intake_course', 'intake_major'])
                ->where(function ($scopeQuery) use ($semesterId, $chargedStudentIds): void {
                    $scopeQuery
                        ->whereIn('id', $chargedStudentIds)
                        ->orWhere(function ($dueQuery) use ($semesterId): void {
                            $dueQuery
                                ->whereNotNull('intake_major')
                                ->where('intake_major', '<=', $semesterId);
                        });
                });
        } else {
            $query
                ->where('status', 'intake_pre_uni_gc')
                ->where(function ($scopeQuery) use ($semesterId, $chargedStudentIds): void {
                    $scopeQuery
                        ->whereIn('id', $chargedStudentIds)
                        ->orWhere(function ($dueQuery) use ($semesterId): void {
                            $dueQuery
                                ->where(function ($majorQuery) use ($semesterId): void {
                                    $majorQuery
                                        ->whereNull('intake_major')
                                        ->orWhere('intake_major', '>', $semesterId);
                                });
                        });
                });
        }

        return $query->pluck('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseStudentQuery(?int $campusId, array $filters)
    {
        $query = Student::query()
            ->with('program')
            ->when($campusId !== null, fn ($builder) => $builder->where('campus_id', $campusId));

        return $this->applyStudentAttributeFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyStudentAttributeFilters($query, array $filters)
    {
        if (! empty($filters['program_id']) && $filters['program_id'] !== 'all') {
            $query->where('program_id', (int) $filters['program_id']);
        }

        if (! empty($filters['intake_semester_id']) && $filters['intake_semester_id'] !== 'all') {
            $query->where('intake_semester_id', (int) $filters['intake_semester_id']);
        }

        if (! empty($filters['cohort']) && $filters['cohort'] !== 'all') {
            $query->where('intake', (int) $filters['cohort']);
        }

        if (! empty($filters['student_status']) && $filters['student_status'] !== 'all') {
            $query->where('status', (string) $filters['student_status']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function studentScopedFilters(array $filters): array
    {
        return [
            'search' => (string) ($filters['search'] ?? ''),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyPaymentStateFilter(Collection $rows, array $filters): Collection
    {
        if (empty($filters['payment_state']) || $filters['payment_state'] === 'all') {
            return $rows;
        }

        return $rows
            ->where('payment_state', (string) $filters['payment_state'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function shouldIncludeSkippedRows(array $filters): bool
    {
        return ($filters['generation_state'] ?? 'all') === 'skipped';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyRowFilters(Collection $rows, array $filters): Collection
    {
        $generationState = (string) ($filters['generation_state'] ?? 'all');

        return $rows
            ->filter(fn (array $row) => $row !== [])
            ->when($generationState !== 'skipped', fn (Collection $collection) => $collection
                ->reject(fn (array $row) => $row['generation_state'] === 'skipped'))
            ->when(! empty($filters['expected_fee_type']) && $filters['expected_fee_type'] !== 'all', function (Collection $collection) use ($filters) {
                return $collection->where('expected_source', (string) $filters['expected_fee_type']);
            })
            ->when($generationState !== 'all' && $generationState !== '', function (Collection $collection) use ($generationState) {
                return $collection->where('generation_state', $generationState);
            })
            ->sortBy([
                ['generation_state', 'asc'],
                ['student.student_code', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function matchesStudentScopedFilters(array $row, array $filters): bool
    {
        if (! empty($filters['program_id']) && $filters['program_id'] !== 'all') {
            $student = $this->studentIndex[$row['student']['id']] ?? Student::query()->find($row['student']['id']);
            if (! $student || (int) $student->program_id !== (int) $filters['program_id']) {
                return false;
            }
        }

        if (! empty($filters['intake_semester_id']) && $filters['intake_semester_id'] !== 'all') {
            if ((int) ($row['intake_semester_id'] ?? 0) !== (int) $filters['intake_semester_id']) {
                return false;
            }
        }

        if (! empty($filters['cohort']) && $filters['cohort'] !== 'all') {
            if ((int) ($row['cohort'] ?? 0) !== (int) $filters['cohort']) {
                return false;
            }
        }

        if (! empty($filters['student_status']) && $filters['student_status'] !== 'all') {
            if (($row['student']['status'] ?? null) !== $filters['student_status']) {
                return false;
            }
        }

        if (! empty($filters['search'])) {
            $search = mb_strtolower((string) $filters['search']);
            $haystack = mb_strtolower(($row['student']['full_name'] ?? '').' '.($row['student']['student_code'] ?? ''));
            if (! str_contains($haystack, $search)) {
                return false;
            }
        }

        return true;
    }

    private function chargeKey(int $studentId, string $chargeType): string
    {
        return $studentId.'|'.$chargeType;
    }

    private function chargePrecedence(FinanceCharge $charge): int
    {
        return $charge->status === FinanceCharge::STATUS_ACTIVE ? 0 : 1;
    }

    private static function statusLabelFor(string $status): string
    {
        return (new Student(['status' => $status]))->status_label;
    }
}
