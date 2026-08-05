<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\DeferCase;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicBillingRegistrationData;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\DTO\StudentDeferActionSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Academic\StudentDeferLifecycleReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class BillingExceptionCollector
{
    /** @var array<string, Collection<int, StudentDeferActionSummary>> */
    private array $deferActionsWithoutCasesByScope = [];

    /** @var array<string, Collection<int, StudentDeferActionSummary>> */
    private array $deferActionsByScope = [];

    /** @var array<string, array{term_number: int|null, amount: float|null, chargeable_term_index: int|null}> */
    private array $tuitionTermDataByStudentSemester = [];

    /** @var array<int, StudentReference> */
    private array $studentReferencesById = [];

    public function __construct(
        private readonly AcademicFinanceChargeSourceGateway $academicSources,
        private readonly StudentDeferLifecycleReader $deferLifecycle,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly StudentChargeTimingResolver $chargeTiming,
        private readonly StudentReferenceReader $studentReferences,
        private readonly ZeroTuitionTermLookup $zeroTuitionTerms,
    ) {}

    public function counts(?int $semesterId, ?int $campusId): array
    {
        $counts = [
            'missing_charge' => 0,
            'zero_tuition_waived' => 0,
            'deferred_enrolled' => 0,
            'retake_no_charge' => 0,
            'defer_no_case' => 0,
            'mismatch' => 0,
        ];
        foreach ($this->classifiedRegistrationChunks($semesterId, $campusId) as $classified) {
            foreach ($classified as $type => $registrations) {
                $counts[$type] += $registrations->count();
            }
        }
        $counts['defer_no_case'] = $this->deferActionsWithoutCases($semesterId, $campusId)->count();

        return $counts;
    }

    public function paginate(
        ?int $semesterId,
        ?string $type,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        if ($type === 'mismatch') {
            return $this->paginator(collect(), $page, $perPage, $path);
        }

        if ($type === 'defer_no_case') {
            $rows = $this->deferActionsWithoutCases($semesterId, $campusId)
                ->map(fn (StudentDeferActionSummary $action): array => $this->mapDeferNoCaseRow($action));

            return $this->paginator($rows, $page, $perPage, $path);
        }

        if ($type !== null && in_array($type, [
            'missing_charge',
            'zero_tuition_waived',
            'deferred_enrolled',
            'retake_no_charge',
        ], true)) {
            return $this->paginateRegistrationTypes(
                $semesterId,
                $campusId,
                [$type],
                $page,
                $perPage,
                $path,
            );
        }

        return $this->paginateRegistrationTypes(
            $semesterId,
            $campusId,
            ['missing_charge', 'zero_tuition_waived', 'deferred_enrolled', 'retake_no_charge'],
            $page,
            $perPage,
            $path,
            includeDeferNoCase: true,
        );
    }

    /**
     * @return iterable<array<string, Collection<int, AcademicBillingRegistrationData>>>
     */
    private function classifiedRegistrationChunks(?int $semesterId, ?int $campusId): iterable
    {
        foreach ($this->academicSources->billingExceptionRegistrationChunks($semesterId) as $registrationChunk) {
            $studentIds = collect($registrationChunk)
                ->pluck('student_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $this->studentReferencesById += $this->studentReferences->findMany($studentIds);
            $registrations = collect($registrationChunk)
                ->filter(fn (AcademicBillingRegistrationData $registration): bool => $campusId === null
                    || $this->studentReference($registration->student_id)?->campusId === $campusId)
                ->values();

            yield $this->classifyRegistrations($registrations, $semesterId, $campusId);

            foreach ($studentIds as $studentId) {
                unset($this->studentReferencesById[$studentId]);
            }
        }
    }

    /**
     * @param  Collection<int, AcademicBillingRegistrationData>  $registrations
     * @return array<string, Collection<int, AcademicBillingRegistrationData>>
     */
    private function classifyRegistrations(Collection $registrations, ?int $semesterId, ?int $campusId): array
    {
        $eligible = $registrations
            ->reject(static fn (AcademicBillingRegistrationData $registration): bool => in_array(
                $registration->registration_status,
                ['defer', 'dropped', 'withdrawn'],
                true,
            ))
            ->values();

        $withoutActiveCharge = $this->withoutActiveCharge($eligible);
        $deferredPairs = $this->deferredEnrollmentPairs($withoutActiveCharge, $semesterId, $campusId);
        $waivedPairs = collect();
        if ($this->zeroTuitionTerms->exists()) {
            $enrollments = $this->programEnrollments->forStudentIds(
                $withoutActiveCharge->pluck('student_id')->unique()->values()->all(),
            );
            $waivedPairs = $withoutActiveCharge
                ->mapWithKeys(fn (AcademicBillingRegistrationData $registration): array => [
                    $this->registrationPairKey($registration) => $this->tuitionTermData(
                        $registration,
                        $enrollments[$registration->student_id],
                    )['amount'] === 0.0,
                ]);
        }

        $deferred = $withoutActiveCharge
            ->filter(fn (AcademicBillingRegistrationData $registration): bool => isset(
                $deferredPairs[$this->registrationPairKey($registration)],
            ))
            ->values();

        $nonDeferred = $withoutActiveCharge
            ->reject(fn (AcademicBillingRegistrationData $registration): bool => isset(
                $deferredPairs[$this->registrationPairKey($registration)],
            ))
            ->values();

        $waived = $nonDeferred
            ->filter(fn (AcademicBillingRegistrationData $registration): bool => $waivedPairs[
                $this->registrationPairKey($registration)
            ] ?? false)
            ->values();

        $missing = $nonDeferred
            ->reject(fn (AcademicBillingRegistrationData $registration): bool => $waivedPairs[
                $this->registrationPairKey($registration)
            ] ?? false)
            ->groupBy('student_id')
            ->map(static fn (Collection $studentRegistrations): AcademicBillingRegistrationData => $studentRegistrations
                ->sortBy('id')
                ->first())
            ->values();

        $waived = $waived
            ->groupBy('student_id')
            ->map(static fn (Collection $studentRegistrations): AcademicBillingRegistrationData => $studentRegistrations
                ->sortBy('id')
                ->first())
            ->values();

        $deferred = $deferred
            ->groupBy('student_id')
            ->map(static fn (Collection $studentRegistrations): AcademicBillingRegistrationData => $studentRegistrations
                ->sortBy('id')
                ->first())
            ->values();

        $retake = $eligible
            ->filter(static fn (AcademicBillingRegistrationData $registration): bool => $registration->is_retake)
            ->values();
        if ($retake->isNotEmpty()) {
            $retakeChargeKeys = $this->activeCanonicalRetakeChargeKeys($retake);
            $retake = $retake
                ->reject(fn (AcademicBillingRegistrationData $registration): bool => $this->hasCanonicalRetakeCharge(
                    $registration,
                    $retakeChargeKeys,
                ))
                ->values();
        }

        return [
            'missing_charge' => $missing,
            'zero_tuition_waived' => $waived,
            'deferred_enrolled' => $deferred,
            'retake_no_charge' => $retake,
        ];
    }

    /**
     * @param  Collection<int, AcademicBillingRegistrationData>  $registrations
     * @return Collection<int, AcademicBillingRegistrationData>
     */
    private function withoutActiveCharge(Collection $registrations): Collection
    {
        if ($registrations->isEmpty()) {
            return collect();
        }

        $activeChargePairs = FinanceCharge::query()
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->whereIn('student_id', $registrations->pluck('student_id')->unique()->all())
            ->get(['student_id', 'semester_id'])
            ->mapWithKeys(static fn (FinanceCharge $charge): array => [
                ((int) $charge->student_id).':'.((int) $charge->semester_id) => true,
            ]);

        return $registrations
            ->reject(fn (AcademicBillingRegistrationData $registration): bool => isset(
                $activeChargePairs[$this->registrationPairKey($registration)],
            ))
            ->values();
    }

    /**
     * @param  Collection<int, AcademicBillingRegistrationData>  $registrations
     * @return array<string, true>
     */
    private function deferredEnrollmentPairs(
        Collection $registrations,
        ?int $semesterId,
        ?int $campusId,
    ): array {
        if ($registrations->isEmpty()) {
            return [];
        }

        $activeActions = $this->deferActions($semesterId, $campusId)
            ->filter(static fn (StudentDeferActionSummary $action): bool => $action->currentlyDeferred);
        if ($activeActions->isEmpty()) {
            return [];
        }

        $activeStudentIds = $activeActions->pluck('studentId')->unique()->all();
        $actionPairs = $activeActions
            ->filter(static fn (StudentDeferActionSummary $action): bool => $action->fromSemesterId !== null)
            ->mapWithKeys(static fn (StudentDeferActionSummary $action): array => [
                "{$action->studentId}:{$action->fromSemesterId}" => true,
            ]);

        $casePairs = DeferCase::query()
            ->whereIn('student_id', $activeStudentIds)
            ->whereIn(
                'semester_id',
                $registrations->pluck('semester_id')->filter()->unique()->all(),
            )
            ->get(['student_id', 'semester_id'])
            ->mapWithKeys(static fn (DeferCase $case): array => [
                ((int) $case->student_id).':'.((int) $case->semester_id) => true,
            ]);

        return $registrations
            ->filter(static fn (AcademicBillingRegistrationData $registration): bool => in_array(
                $registration->student_id,
                $activeStudentIds,
                true,
            ))
            ->filter(fn (AcademicBillingRegistrationData $registration): bool => isset(
                $actionPairs[$this->registrationPairKey($registration)],
            ) || isset($casePairs[$this->registrationPairKey($registration)]))
            ->mapWithKeys(fn (AcademicBillingRegistrationData $registration): array => [
                $this->registrationPairKey($registration) => true,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, AcademicBillingRegistrationData>  $registrations
     * @return Collection<string, true>
     */
    private function activeCanonicalRetakeChargeKeys(Collection $registrations): Collection
    {
        $legacySourceRefs = $registrations
            ->map(static fn (AcademicBillingRegistrationData $registration): string => "legacy-course-registration:{$registration->id}")
            ->all();
        $academicSourceRefs = $registrations
            ->flatMap(static fn (AcademicBillingRegistrationData $registration): array => $registration->retake_source_refs)
            ->unique()
            ->values()
            ->all();

        return FinanceObligation::query()
            ->join('finance_charges', 'finance_charges.finance_obligation_id', '=', 'finance_obligations.id')
            ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
            ->where(function ($query) use ($legacySourceRefs, $academicSourceRefs): void {
                $query->where(function ($legacy) use ($legacySourceRefs): void {
                    $legacy
                        ->where('finance_obligations.source_system', 'finance')
                        ->where('finance_obligations.source_kind', 'legacy_course_registration')
                        ->whereIn('finance_obligations.source_ref', $legacySourceRefs);
                });

                if ($academicSourceRefs !== []) {
                    $query->orWhere(function ($academic) use ($academicSourceRefs): void {
                        $academic
                            ->where('finance_obligations.source_system', AcademicFinanceSourceKeys::SOURCE_SYSTEM)
                            ->where('finance_obligations.source_kind', AcademicFinanceSourceKeys::COURSE_RETAKE_REGISTRATION)
                            ->whereIn('finance_obligations.source_ref', $academicSourceRefs);
                    });
                }
            })
            ->get([
                'finance_obligations.source_system',
                'finance_obligations.source_kind',
                'finance_obligations.source_ref',
            ])
            ->mapWithKeys(static fn (FinanceObligation $obligation): array => [
                self::sourceKey(
                    (string) $obligation->source_system,
                    (string) $obligation->source_kind,
                    (string) $obligation->source_ref,
                ) => true,
            ]);
    }

    /** @param Collection<string, true> $activeChargeKeys */
    private function hasCanonicalRetakeCharge(
        AcademicBillingRegistrationData $registration,
        Collection $activeChargeKeys,
    ): bool {
        $legacyRegistrationKey = self::sourceKey(
            'finance',
            'legacy_course_registration',
            "legacy-course-registration:{$registration->id}",
        );
        if (isset($activeChargeKeys[$legacyRegistrationKey])) {
            return true;
        }

        foreach ($registration->retake_source_refs as $sourceRef) {
            $key = self::sourceKey(
                AcademicFinanceSourceKeys::SOURCE_SYSTEM,
                AcademicFinanceSourceKeys::COURSE_RETAKE_REGISTRATION,
                $sourceRef,
            );
            if (isset($activeChargeKeys[$key])) {
                return true;
            }
        }

        return false;
    }

    private function studentReference(int $studentId): ?StudentReference
    {
        return $this->studentReferencesById[$studentId] ?? null;
    }

    /**
     * @return array{term_number: int|null, amount: float|null, chargeable_term_index: int|null}
     */
    private function tuitionTermData(
        AcademicBillingRegistrationData $registration,
        ?ProgramEnrollmentSummary $enrollment = null,
    ): array {
        $semesterId = $registration->semester_id ?? $registration->offering_semester_id;
        if ($semesterId === null) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $enrollment ??= $this->programEnrollments->forStudentId($registration->student_id);
        $key = implode(':', [
            $enrollment->curriculumVersionId ?? 'none',
            $enrollment->intakeSemesterId ?? 'none',
            $enrollment->intakeMajorSemesterId ?? 'none',
            $semesterId,
        ]);

        return $this->tuitionTermDataByStudentSemester[$key] ??= $this->chargeTiming->getTuitionTermData(
            $enrollment,
            $semesterId,
        );
    }

    private function mapRegistrationRow(
        string $type,
        AcademicBillingRegistrationData $registration,
        ?int $semesterId,
    ): array {
        return match ($type) {
            'missing_charge' => $this->mapMissingChargeRow($registration, $semesterId),
            'zero_tuition_waived' => $this->mapZeroTuitionWaivedRow($registration, $semesterId),
            'deferred_enrolled' => $this->mapDeferredEnrolledRow($registration, $semesterId),
            'retake_no_charge' => $this->mapRetakeNoChargeRow($registration, $semesterId),
            default => throw new \RuntimeException("Unknown billing exception type: {$type}"),
        };
    }

    private function mapMissingChargeRow(
        AcademicBillingRegistrationData $registration,
        ?int $semesterId,
    ): array {
        return $this->baseRegistrationRow(
            type: 'missing_charge',
            registration: $registration,
            description: 'Sinh viên đăng ký học nhưng chưa có phí học kỳ active cần thu',
            severity: 'high',
            context: [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            fixable: true,
        );
    }

    private function mapZeroTuitionWaivedRow(
        AcademicBillingRegistrationData $registration,
        ?int $semesterId,
    ): array {
        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->offering_semester_id;

        return $this->baseRegistrationRow(
            type: 'zero_tuition_waived',
            registration: $registration,
            description: 'Tuition plan kỳ này có mức phí 0 — không cần sinh charge',
            severity: 'low',
            context: [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                'tuition_term_number' => $this->tuitionTermData($registration)['term_number'],
            ],
            fixable: false,
        );
    }

    private function mapDeferredEnrolledRow(
        AcademicBillingRegistrationData $registration,
        ?int $semesterId,
    ): array {
        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->offering_semester_id;
        $deferCase = $resolvedSemesterId === null
            ? null
            : DeferCase::query()
                ->where('student_id', $registration->student_id)
                ->where('semester_id', $resolvedSemesterId)
                ->first();

        $description = match ($deferCase?->fee_policy) {
            DeferCase::POLICY_FORFEIT => 'Sinh viên đã bảo lưu (forfeit phí) nhưng vẫn giữ đăng ký lớp — không cần sinh phí học kỳ',
            DeferCase::POLICY_PRESERVE => 'Sinh viên đã bảo lưu (preserve phí) nhưng vẫn giữ đăng ký lớp — không sinh phí mới học kỳ này',
            DeferCase::POLICY_PARTIAL => 'Sinh viên đã bảo lưu (partial phí) nhưng vẫn giữ đăng ký lớp — kiểm tra defer_case trước khi sinh phí',
            default => 'Sinh viên đã bảo lưu học kỳ này nhưng vẫn giữ đăng ký lớp — không cần sinh phí học kỳ',
        };

        return $this->baseRegistrationRow(
            type: 'deferred_enrolled',
            registration: $registration,
            description: $description,
            severity: 'low',
            context: [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                // students.status is legacy; prefer the live enrollment projection.
                'student_status' => $this->programEnrollments->forStudentId($registration->student_id)->legacyCompatibleStatus(),
                'defer_case_id' => $deferCase?->id,
                'fee_policy' => $deferCase?->fee_policy,
            ],
            fixable: false,
        );
    }

    private function mapRetakeNoChargeRow(
        AcademicBillingRegistrationData $registration,
        ?int $semesterId,
    ): array {
        return $this->baseRegistrationRow(
            type: 'retake_no_charge',
            registration: $registration,
            description: 'Sinh viên học lại nhưng chưa có phí retake active',
            severity: 'medium',
            context: [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            fixable: true,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function baseRegistrationRow(
        string $type,
        AcademicBillingRegistrationData $registration,
        string $description,
        string $severity,
        array $context,
        bool $fixable,
    ): array {
        return [
            'type' => $type,
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $this->studentReference($registration->student_id)?->studentCode,
            'student_name' => $this->studentReference($registration->student_id)?->fullName,
            'description' => $description,
            'severity' => $severity,
            'context' => $context,
            'created_at' => $this->createdAtIso($registration->created_at),
            'fixable' => $fixable,
        ];
    }

    private function mapDeferNoCaseRow(StudentDeferActionSummary $action): array
    {
        return [
            'type' => 'defer_no_case',
            'source_id' => $action->id,
            'student_id' => $action->studentId,
            'student_code' => $action->studentCode,
            'student_name' => $action->studentName,
            'description' => 'Defer action ghi nhận nhưng chưa có defer_case',
            'severity' => 'medium',
            'context' => [
                'student_action_log_id' => $action->id,
                'from_semester_id' => $action->fromSemesterId,
            ],
            'created_at' => $action->createdAt ?? now()->toISOString(),
            'fixable' => false,
        ];
    }

    /**
     * @param  list<string>  $types
     */
    private function paginateRegistrationTypes(
        ?int $semesterId,
        ?int $campusId,
        array $types,
        int $page,
        int $perPage,
        string $path,
        bool $includeDeferNoCase = false,
    ): LengthAwarePaginator {
        $through = $page * $perPage;
        $total = 0;
        $rows = collect();
        $compareType = count($types) > 1 || $includeDeferNoCase;

        foreach ($this->classifiedRegistrationChunks($semesterId, $campusId) as $classified) {
            foreach ($types as $type) {
                $registrations = $classified[$type];
                $total += $registrations->count();
                $rows->push(...$registrations
                    ->map(fn (AcademicBillingRegistrationData $registration): array => $this->mapRegistrationRow(
                        $type,
                        $registration,
                        $semesterId,
                    ))
                    ->all());
            }

            $rows = $rows
                ->sort(fn (array $left, array $right): int => $this->compareRows(
                    $left,
                    $right,
                    $compareType,
                ))
                ->take($through)
                ->values();
        }

        if ($includeDeferNoCase) {
            $deferRows = $this->deferActionsWithoutCases($semesterId, $campusId)
                ->map(fn (StudentDeferActionSummary $action): array => $this->mapDeferNoCaseRow($action));
            $total += $deferRows->count();
            $rows->push(...$deferRows->all());
            $rows = $rows
                ->sort(fn (array $left, array $right): int => $this->compareRows(
                    $left,
                    $right,
                    true,
                ))
                ->take($through)
                ->values();
        }

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'page'],
        );
    }

    /** @return Collection<int, StudentDeferActionSummary> */
    private function deferActionsWithoutCases(?int $semesterId, ?int $campusId): Collection
    {
        $scopeKey = $this->scopeKey($semesterId, $campusId);

        return $this->deferActionsWithoutCasesByScope[$scopeKey] ??= (function () use ($semesterId, $campusId): Collection {
            $actions = $this->deferActions($semesterId, $campusId);
            if ($actions->isEmpty()) {
                return collect();
            }

            $caseActionIds = DeferCase::query()
                ->whereIn('student_action_log_id', $actions->pluck('id'))
                ->pluck('student_action_log_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all();

            return $actions
                ->reject(static fn (StudentDeferActionSummary $action): bool => in_array(
                    $action->id,
                    $caseActionIds,
                    true,
                ))
                ->values();
        })();
    }

    /** @return Collection<int, StudentDeferActionSummary> */
    private function deferActions(?int $semesterId, ?int $campusId): Collection
    {
        $scopeKey = $this->scopeKey($semesterId, $campusId);

        return $this->deferActionsByScope[$scopeKey] ??= collect(
            $this->deferLifecycle->listDeferActions($semesterId, $campusId),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginator(
        Collection $rows,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'page'],
        );
    }

    private function compareRows(array $left, array $right, bool $compareType = false): int
    {
        $timestampComparison = $this->createdAtTimestamp($right['created_at'] ?? null)
            <=> $this->createdAtTimestamp($left['created_at'] ?? null);
        if ($timestampComparison !== 0) {
            return $timestampComparison;
        }

        if ($compareType) {
            $typeComparison = ((string) $left['type']) <=> ((string) $right['type']);
            if ($typeComparison !== 0) {
                return $typeComparison;
            }
        }

        return ((int) $right['source_id']) <=> ((int) $left['source_id']);
    }

    private function registrationPairKey(AcademicBillingRegistrationData $registration): string
    {
        return "{$registration->student_id}:".((int) $registration->semester_id);
    }

    private function scopeKey(?int $semesterId, ?int $campusId): string
    {
        return ($semesterId ?? 'all').':'.($campusId ?? 'all');
    }

    private static function sourceKey(string $sourceSystem, string $sourceKind, string $sourceRef): string
    {
        return "{$sourceSystem}:{$sourceKind}:{$sourceRef}";
    }

    private function createdAtIso(mixed $createdAt): string
    {
        if ($createdAt instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($createdAt)->toISOString();
        }

        if (is_string($createdAt) && $createdAt !== '') {
            return CarbonImmutable::parse($createdAt)->toISOString();
        }

        return now()->toISOString();
    }

    private function createdAtTimestamp(mixed $createdAt): int
    {
        if ($createdAt instanceof \DateTimeInterface) {
            return $createdAt->getTimestamp();
        }

        if (is_string($createdAt) && $createdAt !== '') {
            return CarbonImmutable::parse($createdAt)->getTimestamp();
        }

        return now()->getTimestamp();
    }
}
