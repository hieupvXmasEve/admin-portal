<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\Reporting\FeeMonitorExpectedFeeCatalog;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionIssue;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListFeeMonitorQuery
{
    /** @var array<string, FinanceCharge> */
    private array $chargeIndex = [];

    /** @var array<int, StudentReference> */
    private array $studentIndex = [];

    /** @var array<int, ProgramEnrollmentSummary> */
    private array $enrollmentIndex = [];

    public function __construct(
        private readonly PreviewMajorChargeGenerationQuery $majorPreviewQuery,
        private readonly PreviewEgcChargeGenerationQuery $egcPreviewQuery,
        private readonly StudentChargeTimingResolver $timingResolver,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly CurrentSettlementPositionPresenter $positionPresenter,
        private readonly AcademicFinanceChargeSourceGateway $academicSources,
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly StudentCollectionEligibilityReader $studentEligibility,
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
        $rows = $rows->merge($this->rowsFromRetakeResitExpectation($semesterId, $campusId, $filters));

        return $this->applyRowFilters($rows, $filters)->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filterOptions(int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $studentIds = $this->studentReferences->idsForCampus($campusId === null ? null : (int) $campusId);
        $students = collect($this->studentReferences->findMany($studentIds));
        $enrollments = collect($this->programEnrollments->forStudentIds($studentIds));
        $programs = $enrollments
            ->filter(fn (ProgramEnrollmentSummary $enrollment): bool => $enrollment->programId !== null)
            ->unique(fn (ProgramEnrollmentSummary $enrollment): ?int => $enrollment->programId)
            ->sortBy(fn (ProgramEnrollmentSummary $enrollment): string => (string) $enrollment->programCode)
            ->map(fn (ProgramEnrollmentSummary $enrollment): array => [
                'value' => $enrollment->programId,
                'label' => $enrollment->programCode.' — '.$enrollment->programName,
            ])
            ->values()
            ->all();

        $intakes = $enrollments
            ->map(fn (ProgramEnrollmentSummary $enrollment): ?int => $enrollment->intakeSemesterId)
            ->filter()
            ->unique()
            ->values();

        $intakeSemesters = collect($this->academicPeriods->findMany($intakes->all()))
            ->sortByDesc(fn ($period) => $period->start_date)
            ->map(fn ($period): array => [
                'value' => $period->id,
                'label' => $period->name,
            ])
            ->values()
            ->all();

        $cohorts = $students
            ->pluck('cohort')
            ->filter(fn ($intake) => $intake !== null)
            ->unique()
            ->sort()
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
            // intake_major still gets HP monitored in the tuition lane, but is hidden
            // from the status filter per ops request (no intake_major cohort in use yet).
            'student_statuses' => collect(['intake_pre_uni_gc', 'intake_course', 'intake_major'])
                ->reject(fn (string $status) => $status === 'intake_major')
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => StudentLifecycleStatusPresenter::label($status),
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
        $this->enrollmentIndex = [];

        $charges = FinanceCharge::query()
            ->with(['invoiceLines', 'financeObligation'])
            ->where('semester_id', $semesterId)
            ->when($campusId !== null, fn ($query) => $query->whereIn(
                'student_id',
                $this->studentReferences->idsForCampus((int) $campusId),
            ))
            ->get();
        $this->primeStudents($charges->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->all());

        foreach ($charges as $charge) {
            $key = $this->chargeKey((int) $charge->student_id, (string) $charge->charge_type);
            $existing = $this->chargeIndex[$key] ?? null;

            if ($existing === null || $this->chargePrecedence($charge) < $this->chargePrecedence($existing)) {
                $this->chargeIndex[$key] = $charge;
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
        $this->primeStudents($classifications->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->all());

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
        $students = $this->candidateStudents($campusId, $filters)
            ->filter(fn (StudentReference $student): bool => in_array($this->liveStatus($student), ['intake_pre_uni_gc', 'intake_course', 'intake_major'], true)
                && ($this->enrollmentIndex[$student->id]->intakeSemesterId ?? null) === $semesterId);

        return $students->map(function (StudentReference $student) use ($semesterId): array {
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
        $registrationIds = $this->academicSources->billingDashboardStudentIds($semesterId);
        $chargedIds = collect($this->chargeIndex)
            ->filter(fn (FinanceCharge $charge): bool => $charge->charge_type === FinanceCharge::TYPE_BHYT)
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $candidateIds = array_values(array_unique([...$registrationIds, ...$chargedIds]));
        $students = $this->candidateStudents($campusId, $filters)
            ->filter(fn (StudentReference $student): bool => in_array($student->id, $candidateIds, true)
                && in_array($this->liveStatus($student), ['intake_pre_uni_gc', 'intake_course', 'intake_major'], true)
                && $this->timingResolver->shouldIncludeStudentForChargeGeneration(
                    $this->enrollmentIndex[$student->id],
                    $semesterId,
                    [FinanceCharge::TYPE_BHYT],
                ));

        return $students->map(function (StudentReference $student) use ($semesterId): array {
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
            if (! in_array($charge->charge_type, $chargeTypes, true)) {
                continue;
            }
            $student = $this->resolveStudent((int) $charge->student_id);
            if ($student === null) {
                continue;
            }

            $source = match ($charge->charge_type) {
                FinanceCharge::TYPE_RETAKE_FEE => FeeMonitorExpectedFeeCatalog::SOURCE_COURSE_RETAKE,
                FinanceCharge::TYPE_EXAM_RESIT_FEE => FeeMonitorExpectedFeeCatalog::SOURCE_EXAM_RESIT,
                default => null,
            };

            $row = $this->buildExpectationRow(
                $student,
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
     * Infer "missing" retake/resit fees from the Academic source contract
     * (ACAD-RET-001): an approved course-retake registration or a chargeable
     * exam-resit attempt that has no Finance charge yet is an expected fee HQ
     * still owes. Rows whose charge already exists are owned by
     * {@see rowsFromExistingSourceCharges}, so this only emits the un-charged
     * (missing) ones — no duplicates. These rows stay invisible until
     * {@see FeeMonitorAcadRetGate::missingInferenceEnabled()} is on (the
     * missing-inference filter in {@see applyRowFilters} drops them otherwise).
     *
     * @param  array<string, mixed>  $filters
     */
    private function rowsFromRetakeResitExpectation(int $semesterId, ?int $campusId, array $filters): Collection
    {
        $rows = collect();

        // Course retake (học lại): non-terminal source still owes a retake_fee.
        // STATUS_CANCELLED is terminal and therefore already excluded.
        foreach ($this->academicSources->retakeStudentIdsWithExpectedFees($semesterId, $campusId) as $studentId) {
            $row = $this->buildMissingSourceRow(
                (int) $studentId,
                FeeMonitorExpectedFeeCatalog::SOURCE_COURSE_RETAKE,
                FinanceCharge::TYPE_RETAKE_FEE,
                $semesterId,
                $filters,
            );
            if ($row !== []) {
                $rows->push($row);
            }
        }

        // Exam resit (thi lại): an in-flight/sat attempt still owes an exam_resit_fee.
        // Cancelled/rejected attempts and an HQ-cancelled fee are excluded.
        foreach ($this->academicSources->examResitStudentIdsWithExpectedFees($semesterId, $campusId) as $studentId) {
            $row = $this->buildMissingSourceRow(
                (int) $studentId,
                FeeMonitorExpectedFeeCatalog::SOURCE_EXAM_RESIT,
                FinanceCharge::TYPE_EXAM_RESIT_FEE,
                $semesterId,
                $filters,
            );
            if ($row !== []) {
                $rows->push($row);
            }
        }

        return $rows;
    }

    /**
     * Build a single "missing" expectation row for an Academic source, or `[]`
     * when a charge already exists (owned elsewhere) or the row is filtered out.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMissingSourceRow(int $studentId, string $source, string $chargeType, int $semesterId, array $filters): array
    {
        if ($this->resolveChargeForStudent($studentId, $chargeType) !== null) {
            return [];
        }

        $student = $this->resolveStudent($studentId);
        if ($student === null) {
            return [];
        }

        $row = $this->buildExpectationRow($student, $source, $chargeType, $semesterId, null, 'missing');

        return $this->matchesStudentScopedFilters($row, $filters) ? $row : [];
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
        StudentReference $student,
        string $source,
        string $chargeType,
        int $semesterId,
        ?FinanceCharge $charge,
        ?string $generationState,
        string $reasonCode = '',
    ): array {
        $generationState ??= $this->resolveGenerationStateFromCharge($charge);
        $amount = $charge !== null ? (float) $charge->amount : null;
        $liveStatus = $this->liveStatus($student);

        return [
            'row_key' => $student->id.'-'.$source.'-'.$semesterId,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->studentCode,
                'full_name' => $student->fullName,
                'status' => $liveStatus,
                'status_label' => StudentLifecycleStatusPresenter::label($liveStatus),
            ],
            'program_code' => $this->enrollmentIndex[$student->id]->programCode ?? null,
            'intake_semester_id' => $this->enrollmentIndex[$student->id]->intakeSemesterId ?? null,
            'cohort' => $student->cohort,
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
            'discount_amount' => null,
            'credit_amount' => null,
            'settlement_valid' => $charge === null ? null : false,
            'settlement_state' => $charge === null ? null : SettlementPosition::STATE_INVALID,
            'settlement_version' => null,
            'settlement_issue_codes' => [],
            'settlement_issues' => [],
            'settlement_breakdown' => [],
            'finance_charge_id' => $charge?->id,
            'source_type' => $charge?->financeObligation?->source_kind,
            'source_id' => null,
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

        $charges = FinanceCharge::query()->with('invoiceLines')->whereIn('id', $chargeIds)->get()->keyBy('id');
        $scopes = [];
        $chargeScopeKeys = [];

        foreach ($charges as $chargeId => $charge) {
            $lineIds = $charge->invoiceLines->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
            $chargeScopeKeys[] = (int) $chargeId;
            $scopes[] = $lineIds === []
                ? SettlementPositionScope::payableLine(0)
                : SettlementPositionScope::payableLines($lineIds);
        }

        $positions = $this->settlementPositionReader->batch($scopes);
        $positionByChargeId = [];
        foreach ($chargeScopeKeys as $index => $chargeId) {
            $positionByChargeId[$chargeId] = $positions[$index] ?? null;
        }

        return $rows->map(function (array $row) use ($positionByChargeId): array {
            $chargeId = $row['finance_charge_id'];
            if ($chargeId === null || $row['generation_state'] !== 'generated') {
                return $row;
            }

            $position = $positionByChargeId[$chargeId] ?? null;
            if (! $position instanceof SettlementPosition || ! $position->isValid() || $position->amounts === null) {
                $row['amount'] = null;
                $row['paid_amount'] = null;
                $row['outstanding_amount'] = null;
                $row['discount_amount'] = null;
                $row['credit_amount'] = null;
                $row['payment_state'] = 'invalid';
                $row['settlement_valid'] = false;
                $row['settlement_state'] = SettlementPosition::STATE_INVALID;
                $row['settlement_version'] = $position?->snapshot_version;
                $row['settlement_issue_codes'] = $position === null
                    ? [SettlementPositionIssue::MISSING_PAYABLE_LINE]
                    : array_values(array_unique(array_map(fn ($issue): string => $issue->code, $position->issues)));
                $row['settlement_issues'] = $position === null ? [] : $this->positionPresenter->issues($position->issues);
                $row['settlement_breakdown'] = $position === null ? [] : $this->positionPresenter->present($position);

                return $row;
            }

            $amounts = $position->amounts;
            $amount = (float) $amounts->gross->amount;
            $paidAmount = (float) $amounts->cash->amount;
            $row['amount'] = $amount;
            $row['paid_amount'] = $paidAmount;
            $row['outstanding_amount'] = (float) $amounts->remaining->amount;
            $row['discount_amount'] = (float) $amounts->discount->amount;
            $row['credit_amount'] = (float) $amounts->credit->amount;
            $row['payment_state'] = $this->resolvePaymentState('generated', $amount, $paidAmount, (float) $amounts->remaining->amount);
            $row['settlement_valid'] = true;
            $row['settlement_state'] = $position->settlement_state;
            $row['settlement_version'] = $position->snapshot_version;
            $row['settlement_issue_codes'] = [];
            $row['settlement_issues'] = [];
            $row['settlement_breakdown'] = $this->positionPresenter->present($position);

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

    private function resolvePaymentState(string $generationState, ?float $amount, float $paidAmount, float $remaining): ?string
    {
        if ($generationState !== 'generated' || $amount === null) {
            return null;
        }

        if ($paidAmount <= 0) {
            return 'outstanding';
        }

        if ($remaining <= 0.01) {
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
            // Retake/resit fees are created by HQ from the Academic source via the
            // DNG worklist, not Batch Studio.
            FeeMonitorExpectedFeeCatalog::SOURCE_COURSE_RETAKE => [
                'label' => 'DNG worklist — Học lại',
                'fee_category' => 'retake',
                'fee_type' => FinanceCharge::TYPE_RETAKE_FEE,
            ],
            FeeMonitorExpectedFeeCatalog::SOURCE_EXAM_RESIT => [
                'label' => 'DNG worklist — Thi lại',
                'fee_category' => 'exam_resit',
                'fee_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
            ],
            default => null,
        };
    }

    /**
     * `StudentReference->status` comes from the legacy `students.status`
     * column, which program-enrollment transitions don't write back to.
     * `enrollmentIndex` is always primed alongside `studentIndex`, so prefer
     * its live projection.
     */
    private function liveStatus(StudentReference $student): string
    {
        return $this->enrollmentIndex[$student->id]?->legacyCompatibleStatus() ?? (string) $student->status;
    }

    private function resolveChargeForStudent(int $studentId, string $chargeType): ?FinanceCharge
    {
        return $this->chargeIndex[$this->chargeKey($studentId, $chargeType)] ?? null;
    }

    private function resolveStudent(int $studentId): ?StudentReference
    {
        if (isset($this->studentIndex[$studentId])) {
            return $this->studentIndex[$studentId];
        }

        $this->primeStudents([$studentId]);

        return $this->studentIndex[$studentId] ?? null;
    }

    /** @param list<int> $studentIds */
    private function primeStudents(array $studentIds): void
    {
        $missingIds = array_values(array_diff(
            array_values(array_unique(array_map('intval', $studentIds))),
            array_keys($this->studentIndex),
        ));
        if ($missingIds === []) {
            return;
        }

        $references = $this->studentReferences->findMany($missingIds);
        $this->studentIndex += $references;
        $this->enrollmentIndex += $this->programEnrollments->forStudentIds(array_keys($references));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentReference>
     */
    private function candidateStudents(?int $campusId, array $filters): Collection
    {
        $studentIds = $this->studentReferences->idsForCampus($campusId === null ? null : (int) $campusId);
        $this->primeStudents($studentIds);

        return collect($studentIds)
            ->map(fn (int $studentId): ?StudentReference => $this->studentIndex[$studentId] ?? null)
            ->filter(fn (?StudentReference $student): bool => $student !== null
                && $this->matchesReferenceFilters($student, $filters))
            ->values();
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
            ->when($campusId !== null, fn ($query) => $query->whereIn(
                'student_id',
                $this->studentReferences->idsForCampus((int) $campusId),
            ))
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $eligibleStudentIds = $this->studentEligibility->eligibleStudentIds(
            [$lane === 'tuition' ? 'tuition' : 'egc'],
            $campusId === null ? null : (int) $campusId,
        );
        $this->primeStudents(array_values(array_unique([...$chargedStudentIds, ...$eligibleStudentIds])));

        return collect(array_values(array_unique([...$chargedStudentIds, ...$eligibleStudentIds])))
            ->filter(function (int $studentId) use ($filters, $semesterId, $lane, $chargedStudentIds): bool {
                $student = $this->studentIndex[$studentId] ?? null;
                $enrollment = $this->enrollmentIndex[$studentId] ?? null;
                if ($student === null || $enrollment === null
                    || ! $this->matchesReferenceFilters($student, $filters)
                    || $enrollment->intakeSemesterId === null
                    || $enrollment->intakeSemesterId > $semesterId) {
                    return false;
                }

                $liveStatus = $enrollment->legacyCompatibleStatus();

                if ($lane === 'tuition') {
                    return in_array($liveStatus, ['intake_course', 'intake_major'], true)
                        && (in_array($studentId, $chargedStudentIds, true)
                            || ($enrollment->intakeMajorSemesterId !== null && $enrollment->intakeMajorSemesterId <= $semesterId));
                }

                return $liveStatus === 'intake_pre_uni_gc'
                    && (in_array($studentId, $chargedStudentIds, true)
                        || $enrollment->intakeMajorSemesterId === null
                        || $enrollment->intakeMajorSemesterId > $semesterId);
            })
            ->values();
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
        $missingInferenceSources = FeeMonitorExpectedFeeCatalog::firstPassMissingInferenceSources();

        return $rows
            ->filter(fn (array $row) => $row !== [])
            // Only mandatory fees may report "missing"; optional fees (BHYT, admission)
            // surface only when a charge already exists.
            ->reject(fn (array $row) => $row['generation_state'] === 'missing'
                && ! in_array($row['expected_source'], $missingInferenceSources, true))
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
            $enrollment = $this->enrollmentIndex[$row['student']['id']] ?? null;
            if ($enrollment?->programId !== (int) $filters['program_id']) {
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

    /** @param array<string, mixed> $filters */
    private function matchesReferenceFilters(StudentReference $student, array $filters): bool
    {
        $enrollment = $this->enrollmentIndex[$student->id] ?? null;
        if (! empty($filters['program_id']) && $filters['program_id'] !== 'all'
            && $enrollment?->programId !== (int) $filters['program_id']) {
            return false;
        }
        if (! empty($filters['intake_semester_id']) && $filters['intake_semester_id'] !== 'all'
            && $enrollment?->intakeSemesterId !== (int) $filters['intake_semester_id']) {
            return false;
        }
        if (! empty($filters['cohort']) && $filters['cohort'] !== 'all'
            && $student->cohort !== (int) $filters['cohort']) {
            return false;
        }
        if (! empty($filters['student_status']) && $filters['student_status'] !== 'all'
            && ($enrollment?->legacyCompatibleStatus() ?? $student->status) !== (string) $filters['student_status']) {
            return false;
        }
        if (! empty($filters['search'])) {
            $search = mb_strtolower((string) $filters['search']);

            return str_contains(mb_strtolower($student->fullName.' '.$student->studentCode), $search);
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
}
