<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\AcademicRecord;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\EgcBlock;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway as AcademicFinanceChargeSourceGatewayContract;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Academic\DTO\AcademicBillingRegistrationData;
use App\Shared\Contracts\Academic\DTO\AcademicChargeHandoffResult;
use App\Shared\Contracts\Academic\DTO\AcademicChargeSourceData;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcademicFinanceChargeSourceGateway implements AcademicFinanceChargeSourceGatewayContract
{
    public function __construct(
        private readonly FinanceIntakeContract $intake,
        private readonly StudentLifecycleStatusReader $lifecycleStatusReader,
    ) {}

    public function billingExceptionRegistrations(?int $semesterId = null): array
    {
        $registrations = $this->billingExceptionRegistrationQuery()
            ->when($semesterId !== null, fn (QueryBuilder $query) => $query->where(
                'billing_exception_offerings.semester_id',
                $semesterId,
            ))
            ->orderBy('course_registrations.id')
            ->get();

        $retakeSourceRefs = $registrations->contains(
            static fn (object $registration): bool => (bool) $registration->is_retake,
        )
            ? $this->retakeSourceRefsByCourseRegistrationIds(
                $registrations
                    ->where('is_retake', true)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            )
            : [];

        return $registrations
            ->map(fn (object $registration): AcademicBillingRegistrationData => $this->billingRegistrationData(
                $registration,
                $retakeSourceRefs[(int) $registration->id] ?? [],
            ))
            ->values()
            ->all();
    }

    /** @return iterable<list<AcademicBillingRegistrationData>> */
    public function billingExceptionRegistrationChunks(
        ?int $semesterId = null,
        int $studentChunkSize = 250,
    ): iterable {
        $registrations = $this->billingExceptionRegistrationQuery()
            ->when($semesterId !== null, fn (QueryBuilder $query) => $query->where(
                'billing_exception_offerings.semester_id',
                $semesterId,
            ))
            ->orderBy('course_registrations.student_id')
            ->orderBy('course_registrations.id')
            ->lazy(1000);
        $chunk = collect();
        $chunkStudentIds = [];

        foreach ($registrations as $registration) {
            $studentId = (int) $registration->student_id;
            if (! isset($chunkStudentIds[$studentId]) && count($chunkStudentIds) >= $studentChunkSize) {
                yield $this->billingRegistrationChunkData($chunk);
                $chunk = collect();
                $chunkStudentIds = [];
            }

            $chunkStudentIds[$studentId] = true;
            $chunk->push($registration);
        }

        if ($chunk->isNotEmpty()) {
            yield $this->billingRegistrationChunkData($chunk);
        }
    }

    public function billingExceptionRegistration(
        int $registrationId,
        bool $lockForUpdate = false,
    ): ?AcademicBillingRegistrationData {
        $query = $this->billingExceptionRegistrationQuery()
            ->where('course_registrations.id', $registrationId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $registration = $query->first();
        if ($registration === null) {
            return null;
        }

        $sourceRefs = (bool) $registration->is_retake
            ? $this->retakeSourceRefsByCourseRegistrationIds([$registrationId])
            : [];

        return $this->billingRegistrationData(
            $registration,
            $sourceRefs[$registrationId] ?? [],
        );
    }

    public function createRetakeCharge(int $registrationId, int $actorId, ?string $description = null): AcademicChargeHandoffResult
    {
        return DB::transaction(function () use ($registrationId, $actorId, $description): AcademicChargeHandoffResult {
            $registration = CourseRetakeRegistration::query()
                ->with('unit:id,code,name')
                ->lockForUpdate()
                ->findOrFail($registrationId);

            if ($registration->status !== CourseRetakeRegistration::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Chỉ có thể tạo charge cho đăng ký ở trạng thái approved.'],
                ]);
            }

            $source = $this->retakeSourceData($registration, $description);
            $result = $this->intake->request(new FinanceIntakeData(
                source_system: AcademicFinanceSourceKeys::SOURCE_SYSTEM,
                source_kind: $source->source_kind,
                source_ref: $source->source_ref,
                financial_effect: FinancialEffect::Debit,
                obligation_type: $source->obligation_type,
                facts: $source->facts,
            ));

            if ($result->finance_obligation_id === null || $result->finance_charge_id === null) {
                throw ValidationException::withMessages([
                    'registration_id' => ['Finance intake did not materialize a retake charge.'],
                ]);
            }

            $registration->transitionToPaymentPending($actorId);
            $registration->refresh();

            return new AcademicChargeHandoffResult(
                source_id: (int) $registration->id,
                source_kind: $source->source_kind,
                source_ref: $source->source_ref,
                obligation_type: $source->obligation_type,
                status: (string) $registration->status,
                hq_fee_status: (string) $registration->hq_fee_status,
                finance_obligation_id: $result->finance_obligation_id,
                finance_charge_id: $result->finance_charge_id,
            );
        });
    }

    public function createExamResitCharge(int $attemptId, int $actorId, ?string $dueDate = null): AcademicChargeHandoffResult
    {
        return DB::transaction(function () use ($attemptId, $actorId, $dueDate): AcademicChargeHandoffResult {
            $attempt = ExamResitAttempt::query()
                ->with('unit:id,code,name')
                ->lockForUpdate()
                ->findOrFail($attemptId);

            if ($attempt->hq_fee_status !== ExamResitAttempt::HQ_FEE_PENDING) {
                throw ValidationException::withMessages([
                    'attempt_id' => ['Chỉ có thể tạo phí cho nguồn thi lại đang chờ HQ tạo phí.'],
                ]);
            }

            $source = $this->examResitSourceData($attempt, $dueDate);
            $result = $this->intake->request(new FinanceIntakeData(
                source_system: AcademicFinanceSourceKeys::SOURCE_SYSTEM,
                source_kind: $source->source_kind,
                source_ref: $source->source_ref,
                financial_effect: FinancialEffect::Debit,
                obligation_type: $source->obligation_type,
                facts: $source->facts,
            ));

            if ($result->finance_obligation_id === null || $result->finance_charge_id === null) {
                throw ValidationException::withMessages([
                    'attempt_id' => ['Finance intake did not materialize an exam resit charge.'],
                ]);
            }

            $attempt->transitionToChargeCreated($actorId);
            $attempt->refresh();

            return new AcademicChargeHandoffResult(
                source_id: (int) $attempt->id,
                source_kind: $source->source_kind,
                source_ref: $source->source_ref,
                obligation_type: $source->obligation_type,
                status: (string) $attempt->status,
                hq_fee_status: (string) $attempt->hq_fee_status,
                finance_obligation_id: $result->finance_obligation_id,
                finance_charge_id: $result->finance_charge_id,
            );
        });
    }

    public function chargeableRetakeSourcesForStudent(int $studentId, int $semesterId, bool $lockForUpdate = false): array
    {
        $query = CourseRetakeRegistration::query()
            ->with('unit:id,code,name')
            ->where('student_id', $studentId)
            ->where(function (Builder $query) use ($semesterId): void {
                $query->where('charge_semester_id', $semesterId)
                    ->orWhere(function (Builder $legacyQuery) use ($semesterId): void {
                        $legacyQuery->whereNull('charge_semester_id')
                            ->where('semester_id', $semesterId);
                    });
            })
            ->whereIn('status', [
                CourseRetakeRegistration::STATUS_APPROVED,
                CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
            ])
            ->whereIn('hq_fee_status', [
                CourseRetakeRegistration::HQ_FEE_PENDING,
                CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
            ])
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->map(fn (CourseRetakeRegistration $registration): AcademicChargeSourceData => $this->retakeSourceData($registration))
            ->values()
            ->all();
    }

    public function chargeableExamResitSourcesForStudent(int $studentId, int $semesterId, bool $lockForUpdate = false): array
    {
        $query = ExamResitAttempt::query()
            ->with('unit:id,code,name')
            ->where('student_id', $studentId)
            ->where('charge_semester_id', $semesterId)
            ->whereIn('status', [
                ExamResitAttempt::STATUS_APPROVED,
                ExamResitAttempt::STATUS_SCHEDULED,
            ])
            ->whereIn('hq_fee_status', [
                ExamResitAttempt::HQ_FEE_PENDING,
                ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
            ])
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->map(fn (ExamResitAttempt $attempt): AcademicChargeSourceData => $this->examResitSourceData($attempt))
            ->values()
            ->all();
    }

    public function approvedRetakeSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array
    {
        return CourseRetakeRegistration::query()
            ->with(['student:id,student_id,full_name,campus_id', 'student.campus:id,name', 'campus:id,name', 'unit:id,code,name', 'semester:id,name'])
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn (Builder $query) => $query->where('semester_id', $semesterId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('student', function (Builder $studentQuery) use ($search): void {
                    $studentQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (CourseRetakeRegistration $registration): AcademicChargeSourceData => $this->retakeSourceData($registration))
            ->values()
            ->all();
    }

    public function approvedExamResitSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array
    {
        return ExamResitAttempt::query()
            ->with(['student:id,student_id,full_name,campus_id', 'student.campus:id,name', 'campus:id,name', 'unit:id,code,name', 'chargeSemester:id,name'])
            ->where('status', ExamResitAttempt::STATUS_APPROVED)
            ->where('hq_fee_status', ExamResitAttempt::HQ_FEE_PENDING)
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn (Builder $query) => $query->where('charge_semester_id', $semesterId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('student', function (Builder $studentQuery) use ($search): void {
                    $studentQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (ExamResitAttempt $attempt): AcademicChargeSourceData => $this->examResitSourceData($attempt))
            ->values()
            ->all();
    }

    public function retakeStudentIdsWithExpectedFees(int $semesterId, ?int $campusId = null): array
    {
        return CourseRetakeRegistration::query()
            ->whereIn('status', CourseRetakeRegistration::NON_TERMINAL_STATUSES)
            ->where(fn (Builder $query) => $query
                ->where('charge_semester_id', $semesterId)
                ->orWhere(fn (Builder $fallback) => $fallback->whereNull('charge_semester_id')->where('semester_id', $semesterId)))
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->distinct()
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function billingDashboardStudentIds(int $semesterId): array
    {
        return array_values(array_unique(array_merge(
            CourseRegistration::query()
                ->where('semester_id', $semesterId)
                ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn'])
                ->pluck('student_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all(),
        )));
    }

    public function billingDashboardRetakeStudentIds(int $semesterId): array
    {
        return CourseRegistration::query()
            ->where('semester_id', $semesterId)
            ->where('is_retake', true)
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function examResitStudentIdsWithExpectedFees(int $semesterId, ?int $campusId = null): array
    {
        return ExamResitAttempt::query()
            ->whereIn('status', [
                ExamResitAttempt::STATUS_APPROVED,
                ExamResitAttempt::STATUS_SCHEDULED,
                ExamResitAttempt::STATUS_COMPLETED,
                ExamResitAttempt::STATUS_NO_SHOW,
            ])
            ->where(fn (Builder $query) => $query
                ->where('hq_fee_status', '!=', ExamResitAttempt::HQ_FEE_CANCELLED)
                ->orWhereNull('hq_fee_status'))
            ->where('charge_semester_id', $semesterId)
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->distinct()
            ->pluck('student_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function examResitDueSourcesByIds(array $attemptIds): array
    {
        $attemptIds = array_values(array_unique(array_filter(array_map('intval', $attemptIds))));
        if ($attemptIds === []) {
            return [];
        }

        $attempts = ExamResitAttempt::query()
            ->with($this->examResitDueRelations())
            ->whereIn('id', $attemptIds)
            ->get();
        $lifecycleStatuses = $this->lifecycleStatuses($attempts);

        return $attempts
            ->mapWithKeys(fn (ExamResitAttempt $attempt): array => [
                (int) $attempt->id => $this->examResitDueData(
                    $attempt,
                    $lifecycleStatuses[(int) $attempt->student_id] ?? null,
                ),
            ])
            ->all();
    }

    public function overdueExamResitDueSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array
    {
        $today = now()->startOfDay();
        $now = now();

        $attempts = ExamResitAttempt::query()
            ->with($this->examResitDueRelations())
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->when($semesterId !== null, fn (Builder $query) => $query->where('operation_semester_id', $semesterId))
            ->whereIn('status', [ExamResitAttempt::STATUS_APPROVED, ExamResitAttempt::STATUS_SCHEDULED])
            ->whereIn('hq_fee_status', [ExamResitAttempt::HQ_FEE_PENDING, ExamResitAttempt::HQ_FEE_CHARGE_CREATED])
            ->where(function (Builder $query) use ($today, $now): void {
                $query->where(function (Builder $deadline) use ($now): void {
                    $deadline->whereNotNull('payment_deadline')
                        ->where('payment_deadline', '<', $now);
                })->orWhereHas('session.roomSlot', function (Builder $slot) use ($today): void {
                    $slot->where('exam_date', '<', $today->toDateString());
                });
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->whereHas('student', fn (Builder $student) => $student
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn (Builder $unit) => $unit
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->get();
        $lifecycleStatuses = $this->lifecycleStatuses($attempts);

        return $attempts
            ->map(fn (ExamResitAttempt $attempt): AcademicExamResitDueData => $this->examResitDueData(
                $attempt,
                $lifecycleStatuses[(int) $attempt->student_id] ?? null,
            ))
            ->values()
            ->all();
    }

    public function touchExamResitDueSources(array $attemptIds, DateTimeInterface $timestamp): int
    {
        $attemptIds = array_values(array_unique(array_filter(array_map('intval', $attemptIds))));
        if ($attemptIds === []) {
            return 0;
        }

        return ExamResitAttempt::query()
            ->whereIn('id', $attemptIds)
            ->update(['last_reminded_at' => $timestamp]);
    }

    public function egcBlocksForStudentSemester(int $studentId, int $semesterId): array
    {
        return EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->orderBy('block_number')
            ->get()
            ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
            ->values()
            ->all();
    }

    public function egcBlocksForStudent(int $studentId): array
    {
        return EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->where('student_id', $studentId)
            ->orderBy('semester_id')
            ->orderBy('block_number')
            ->get()
            ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
            ->values()
            ->all();
    }

    public function egcBlocksForSemester(int $semesterId): array
    {
        return EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->where('semester_id', $semesterId)
            ->orderBy('student_id')
            ->orderBy('block_number')
            ->get()
            ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
            ->values()
            ->all();
    }

    public function failedEgcBlocksForSemester(int $semesterId, ?array $studentIds = null, ?int $campusId = null): array
    {
        return EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->where('semester_id', $semesterId)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->when($studentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $studentIds))
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas('student', fn (Builder $student) => $student->where('campus_id', $campusId)))
            ->orderBy('student_id')
            ->orderBy('block_number')
            ->get()
            ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
            ->values()
            ->all();
    }

    public function egcRetakeTargetsForBlock(int $sourceBlockId): array
    {
        $sourceBlock = EgcBlock::query()->find($sourceBlockId);
        if (! $sourceBlock instanceof EgcBlock) {
            return [];
        }

        $query = EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->where('student_id', $sourceBlock->student_id)
            ->where('result', EgcBlock::RESULT_PENDING)
            ->whereKeyNot($sourceBlock->id);

        if ((int) $sourceBlock->block_number === 1) {
            return $query
                ->where('semester_id', $sourceBlock->semester_id)
                ->where('block_number', 2)
                ->orderBy('id')
                ->get()
                ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
                ->values()
                ->all();
        }

        if ((int) $sourceBlock->block_number !== 2) {
            return [];
        }

        $nextSemesterId = $this->nextSemesterIdForEgcBlock($sourceBlock);
        if ($nextSemesterId === null) {
            return [];
        }

        return $query
            ->where('semester_id', $nextSemesterId)
            ->where('block_number', 1)
            ->orderBy('id')
            ->get()
            ->map(fn (EgcBlock $block): AcademicEgcBlockData => $this->egcBlockData($block))
            ->values()
            ->all();
    }

    public function egcBlockById(int $blockId): ?AcademicEgcBlockData
    {
        $block = EgcBlock::query()
            ->with($this->egcBlockRelations())
            ->find($blockId);

        return $block instanceof EgcBlock ? $this->egcBlockData($block) : null;
    }

    public function createEgcBlock(
        int $studentId,
        int $semesterId,
        int $blockNumber,
        int $levelNumber,
        bool $isRetake,
    ): AcademicEgcBlockData {
        $block = EgcBlock::query()->create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'block_number' => $blockNumber,
            'level_number' => $levelNumber,
            'result' => EgcBlock::RESULT_PENDING,
            'is_retake' => $isRetake,
        ]);

        return $this->egcBlockData($block->load($this->egcBlockRelations()));
    }

    public function updateEgcBlockResult(int $blockId, string $result, ?float $attendanceRate): void
    {
        EgcBlock::query()
            ->whereKey($blockId)
            ->update([
                'result' => $result,
                'attendance_rate' => $attendanceRate,
                'synced_at' => now(),
            ]);
    }

    public function updateEgcBlockLevel(int $blockId, int $levelNumber, bool $isRetake): void
    {
        EgcBlock::query()
            ->whereKey($blockId)
            ->update([
                'level_number' => $levelNumber,
                'is_retake' => $isRetake,
            ]);
    }

    public function markEgcRetakeDiscountConsumed(int $blockId, int $discountId): void
    {
        EgcBlock::query()
            ->whereKey($blockId)
            ->update(['retake_discount_id' => $discountId]);
    }

    public function isStudentStudyingEgcLevel(int $studentId, int $levelNumber): bool
    {
        return DB::table('academic_records as ar')
            ->join('units', 'ar.unit_id', '=', 'units.id')
            ->where('ar.student_id', $studentId)
            ->where('units.unit_type', 'egc')
            ->where('units.level', $levelNumber)
            ->where('ar.completion_status', 'in_progress')
            ->where('ar.semester_id', function ($query) use ($studentId): void {
                $query->selectRaw('MAX(ar2.semester_id)')
                    ->from('academic_records as ar2')
                    ->join('units as u2', 'ar2.unit_id', '=', 'u2.id')
                    ->where('ar2.student_id', $studentId)
                    ->where('u2.unit_type', 'egc');
            })
            ->exists();
    }

    public function isEgcRetakeEligible(int $studentId, int $levelNumber): bool
    {
        return EgcBlock::query()
            ->where('student_id', $studentId)
            ->where('level_number', $levelNumber)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->where('attendance_rate', '>=', 80)
            ->whereNull('retake_discount_id')
            ->exists();
    }

    public function egcSourceRef(int $blockId): string
    {
        return AcademicFinanceSourceKeys::egcBlockRef($blockId);
    }

    /**
     * @return array{result:string, attendance_rate:float|null}|null
     */
    public function resolveEgcBlockResult(int $studentId, int $semesterId, int $levelNumber): ?array
    {
        $records = AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereHas('unit', fn (Builder $query) => $query->where('unit_type', 'egc')->where('level', $levelNumber))
            ->orderByDesc('id')
            ->get();

        return $this->resolveEgcResultFromRecords($records);
    }

    public function resolveEgcBlockResultsByBlockIds(array $blockIds): array
    {
        $blockIds = array_values(array_unique(array_filter(array_map('intval', $blockIds))));
        if ($blockIds === []) {
            return [];
        }

        $blocks = EgcBlock::query()
            ->whereIn('id', $blockIds)
            ->orderBy('student_id')
            ->orderBy('semester_id')
            ->orderBy('block_number')
            ->get();

        $resolvedByBlock = [];
        foreach ($blocks->groupBy(fn (EgcBlock $block): string => "{$block->student_id}:{$block->semester_id}") as $groupedBlocks) {
            /** @var Collection<int, EgcBlock> $groupedBlocks */
            $first = $groupedBlocks->first();
            if (! $first instanceof EgcBlock) {
                continue;
            }

            $resolvedByBlock += $this->resolveEgcResultsForStudent(
                (int) $first->student_id,
                (int) $first->semester_id,
                $groupedBlocks,
            );
        }

        return $resolvedByBlock;
    }

    public function hasImmediateNextEgcBlockProgressionEvidence(int $blockId): bool
    {
        $block = EgcBlock::query()->find($blockId);
        if (! $block instanceof EgcBlock) {
            return false;
        }

        $nextBlock = $this->immediateNextEgcBlock($block);
        if (! $nextBlock instanceof EgcBlock) {
            return false;
        }

        // egc_blocks.level_number is provisioned optimistically (block N+1 = level N+1) before
        // results are known, so it alone never proves progression. Trust the level the student
        // is actually registered into when that evidence exists.
        $nextLevel = $this->registeredEgcLevelForBlock($nextBlock) ?? (int) $nextBlock->level_number;

        return $nextLevel > (int) $block->level_number;
    }

    public function egcBlockHasMatchedRegistration(int $blockId): bool
    {
        $block = EgcBlock::query()->find($blockId);
        if (! $block instanceof EgcBlock) {
            return false;
        }

        $registrationIds = DB::table('course_registrations')
            ->join('course_offerings', 'course_registrations.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_registrations.student_id', $block->student_id)
            ->where('course_offerings.semester_id', $block->semester_id)
            ->whereNotIn('course_registrations.registration_status', ['defer', 'dropped', 'withdrawn'])
            ->where('units.unit_type', 'egc')
            ->orderBy('course_registrations.registration_date')
            ->orderBy('course_registrations.id')
            ->pluck('course_registrations.id')
            ->values();

        return $registrationIds->get(((int) $block->block_number) - 1) !== null;
    }

    private function retakeSourceData(CourseRetakeRegistration $registration, ?string $description = null): AcademicChargeSourceData
    {
        $unit = $registration->unit;

        return new AcademicChargeSourceData(
            id: (int) $registration->id,
            student_id: (int) $registration->student_id,
            semester_id: (int) ($registration->charge_semester_id ?? $registration->semester_id),
            campus_id: (int) $registration->campus_id,
            source_kind: AcademicFinanceSourceKeys::COURSE_RETAKE_REGISTRATION,
            source_ref: AcademicFinanceSourceKeys::courseRetakeRegistrationRef((int) $registration->id),
            obligation_type: AcademicFinanceSourceKeys::RETAKE_FEE,
            facts: [
                'student_id' => (int) $registration->student_id,
                'semester_id' => (int) ($registration->charge_semester_id ?? $registration->semester_id),
                'campus_id' => (int) $registration->campus_id,
                'unit_id' => (int) $registration->unit_id,
                'course_offering_id' => $registration->course_offering_id,
                'original_academic_record_id' => $registration->original_academic_record_id,
                'original_semester_id' => $registration->original_semester_id,
                'operation_semester_id' => $registration->operation_semester_id,
                'charge_semester_id' => $registration->charge_semester_id,
                'attempt_number' => $registration->attempt_number,
                'snapshot_amount' => $registration->retake_fee === null ? null : (float) $registration->retake_fee,
                'unit_code' => $unit?->code,
                'unit_name' => $unit?->name,
                'semester_name' => $registration->semester?->name,
                'student_code' => $registration->student?->student_id,
                'student_name' => $registration->student?->full_name,
                'campus_name' => ($registration->campus ?? $registration->student?->campus)?->name,
                'description' => $description ?? "Phí học lại: {$unit?->code} - {$unit?->name}",
            ],
            status: (string) $registration->status,
            hq_fee_status: (string) $registration->hq_fee_status,
        );
    }

    /**
     * @param  list<int>  $registrationIds
     * @return array<int, list<string>>
     */
    private function retakeSourceRefsByCourseRegistrationIds(array $registrationIds): array
    {
        if ($registrationIds === []) {
            return [];
        }

        return CourseRetakeRegistration::query()
            ->whereIn('course_registration_id', $registrationIds)
            ->where('status', '!=', CourseRetakeRegistration::STATUS_CANCELLED)
            ->orderBy('id')
            ->get(['id', 'course_registration_id'])
            ->groupBy('course_registration_id')
            ->map(fn (Collection $registrations): array => $registrations
                ->map(static fn (CourseRetakeRegistration $registration): string => AcademicFinanceSourceKeys::courseRetakeRegistrationRef(
                    (int) $registration->id,
                ))
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $retakeSourceRefs
     */
    /**
     * @param  Collection<int, object>  $registrations
     * @return list<AcademicBillingRegistrationData>
     */
    private function billingRegistrationChunkData(Collection $registrations): array
    {
        $retakeSourceRefs = $this->retakeSourceRefsByCourseRegistrationIds(
            $registrations
                ->where('is_retake', true)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        return $registrations
            ->map(fn (object $registration): AcademicBillingRegistrationData => $this->billingRegistrationData(
                $registration,
                $retakeSourceRefs[(int) $registration->id] ?? [],
            ))
            ->values()
            ->all();
    }

    private function billingRegistrationData(
        object $registration,
        array $retakeSourceRefs,
    ): AcademicBillingRegistrationData {
        return new AcademicBillingRegistrationData(
            id: (int) $registration->id,
            student_id: (int) $registration->student_id,
            semester_id: $registration->semester_id === null ? null : (int) $registration->semester_id,
            course_offering_id: (int) $registration->course_offering_id,
            registration_status: (string) $registration->registration_status,
            is_retake: (bool) $registration->is_retake,
            created_at: $registration->created_at === null ? null : (string) $registration->created_at,
            offering_semester_id: $registration->offering_semester_id === null
                ? null
                : (int) $registration->offering_semester_id,
            unit_name: $registration->unit_name,
            unit_code: $registration->unit_code,
            retake_source_refs: $retakeSourceRefs,
        );
    }

    private function billingExceptionRegistrationQuery(): QueryBuilder
    {
        return DB::table('course_registrations')
            ->leftJoin(
                'course_offerings as billing_exception_offerings',
                'billing_exception_offerings.id',
                '=',
                'course_registrations.course_offering_id',
            )
            ->leftJoin(
                'units as billing_exception_units',
                'billing_exception_units.id',
                '=',
                'billing_exception_offerings.unit_id',
            )
            ->select([
                'course_registrations.id',
                'course_registrations.student_id',
                'course_registrations.semester_id',
                'course_registrations.course_offering_id',
                'course_registrations.registration_status',
                'course_registrations.is_retake',
                'course_registrations.created_at',
                'billing_exception_offerings.semester_id as offering_semester_id',
                'billing_exception_units.name as unit_name',
                'billing_exception_units.code as unit_code',
            ]);
    }

    private function examResitSourceData(ExamResitAttempt $attempt, ?string $dueDate = null): AcademicChargeSourceData
    {
        $unit = $attempt->unit;
        $facts = [
            'student_id' => (int) $attempt->student_id,
            'semester_id' => (int) $attempt->charge_semester_id,
            'campus_id' => (int) $attempt->campus_id,
            'unit_id' => (int) $attempt->unit_id,
            'academic_record_id' => $attempt->academic_record_id,
            'original_course_offering_id' => $attempt->original_course_offering_id,
            'original_semester_id' => $attempt->original_semester_id,
            'operation_semester_id' => $attempt->operation_semester_id,
            'charge_semester_id' => $attempt->charge_semester_id,
            'request_sequence' => $attempt->request_sequence,
            'syllabus_template_id' => $attempt->syllabus_template_id,
            'max_attempts_snapshot' => $attempt->max_attempts_snapshot,
            'late_payment_grace_days_snapshot' => $attempt->late_payment_grace_days_snapshot,
            'allow_unpaid_sitting_snapshot' => $attempt->allow_unpaid_sitting_snapshot,
            'snapshot_amount' => $attempt->fee_amount === null ? null : (float) $attempt->fee_amount,
            'unit_code' => $unit?->code,
            'unit_name' => $unit?->name,
            'semester_name' => $attempt->chargeSemester?->name,
            'student_code' => $attempt->student?->student_id,
            'student_name' => $attempt->student?->full_name,
            'campus_name' => ($attempt->campus ?? $attempt->student?->campus)?->name,
            'description' => "Phí thi lại: {$unit?->code} - {$unit?->name}",
        ];

        if ($dueDate !== null && $dueDate !== '') {
            $facts['due_date'] = $dueDate;
        }

        return new AcademicChargeSourceData(
            id: (int) $attempt->id,
            student_id: (int) $attempt->student_id,
            semester_id: (int) $attempt->charge_semester_id,
            campus_id: (int) $attempt->campus_id,
            source_kind: AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT,
            source_ref: AcademicFinanceSourceKeys::examResitAttemptRef((int) $attempt->id),
            obligation_type: AcademicFinanceSourceKeys::EXAM_RESIT_FEE,
            facts: $facts,
            status: (string) $attempt->status,
            hq_fee_status: (string) $attempt->hq_fee_status,
        );
    }

    /**
     * @return list<string>
     */
    private function examResitDueRelations(): array
    {
        return [
            'student:id,student_id,full_name,email,status',
            'unit:id,code,name',
            'session:id,exam_room_slot_id,unit_id,status',
            'session.roomSlot:id,room_id,exam_date,start_time,end_time',
            'session.roomSlot.room:id,name,code',
        ];
    }

    /**
     * `students.status` is legacy and is not written back on program-enrollment
     * transitions, so the student lifecycle exposed to Finance is read from the
     * Progression-owned projection, falling back to the column only for
     * students that were never materialized.
     *
     * @param  Collection<int, ExamResitAttempt>  $attempts
     * @return array<int, string>
     */
    private function lifecycleStatuses(Collection $attempts): array
    {
        $studentIds = $attempts
            ->filter(static fn (ExamResitAttempt $attempt): bool => $attempt->student !== null)
            ->pluck('student_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return [];
        }

        return $this->lifecycleStatusReader->statusesFor($studentIds);
    }

    private function examResitDueData(ExamResitAttempt $attempt, ?string $lifecycleStatus = null): AcademicExamResitDueData
    {
        $student = $attempt->student;
        $status = $lifecycleStatus ?? $student?->status;
        $slot = $attempt->session?->roomSlot;
        $room = $slot?->room;
        $unit = $attempt->unit;

        return new AcademicExamResitDueData(
            id: (int) $attempt->id,
            student_id: (int) $attempt->student_id,
            campus_id: (int) $attempt->campus_id,
            semester_id: (int) $attempt->charge_semester_id,
            status: (string) $attempt->status,
            hq_fee_status: (string) $attempt->hq_fee_status,
            amount: $attempt->fee_amount === null ? null : (float) $attempt->fee_amount,
            unpaid_allowed_reason: $attempt->unpaid_allowed_reason,
            late_payment_grace_days_snapshot: $attempt->late_payment_grace_days_snapshot,
            payment_deadline: $attempt->payment_deadline?->toIso8601String(),
            last_reminded_at: $attempt->last_reminded_at?->toIso8601String(),
            student_code: $student?->student_id,
            student_name: $student?->full_name,
            student_email: $student?->email,
            student_status: $status,
            student_status_label: $student === null ? null : StudentLifecycleStatusPresenter::label($status),
            student_status_color: $student === null ? null : StudentLifecycleStatusPresenter::color($status),
            unit_code: $unit?->code,
            unit_name: $unit?->name,
            exam_date: $slot?->exam_date?->toDateString(),
            exam_start_time: $slot?->start_time?->format('H:i'),
            exam_end_time: $slot?->end_time?->format('H:i'),
            room_name: $room?->name,
            room_code: $room?->code,
        );
    }

    /**
     * @return list<string>
     */
    private function egcBlockRelations(): array
    {
        return ['student:id,full_name,student_id,status,gc_total_levels,campus_id', 'semester:id,name,start_date,is_archived'];
    }

    private function egcBlockData(EgcBlock $block): AcademicEgcBlockData
    {
        return new AcademicEgcBlockData(
            id: (int) $block->id,
            student_id: (int) $block->student_id,
            semester_id: (int) $block->semester_id,
            block_number: (int) $block->block_number,
            level_number: (int) $block->level_number,
            result: (string) $block->result,
            is_retake: (bool) $block->is_retake,
            attendance_rate: $block->attendance_rate === null ? null : (float) $block->attendance_rate,
            retake_discount_id: $block->retake_discount_id === null ? null : (int) $block->retake_discount_id,
            student_code: $block->student?->student_id,
            student_name: $block->student?->full_name,
            student_status: $block->student?->status,
            campus_id: $block->student?->campus_id === null ? null : (int) $block->student->campus_id,
            student_total_levels: $block->student?->gc_total_levels === null ? null : (int) $block->student->gc_total_levels,
            semester_name: $block->semester?->name,
            synced_at: $block->synced_at?->toIso8601String(),
        );
    }

    private function nextSemesterIdForEgcBlock(EgcBlock $sourceBlock): ?int
    {
        $sourceSemester = Semester::query()->find($sourceBlock->semester_id);
        if (! $sourceSemester instanceof Semester || $sourceSemester->start_date === null) {
            return null;
        }

        $nextId = Semester::query()
            ->where('id', '!=', $sourceSemester->id)
            ->where('is_archived', false)
            ->where('start_date', '>', $sourceSemester->start_date)
            ->orderBy('start_date')
            ->orderBy('id')
            ->value('id');

        return $nextId !== null ? (int) $nextId : null;
    }

    /**
     * @param  Collection<int, EgcBlock>  $blocks
     * @return array<int, array{result:string, attendance_rate:float|null}|null>
     */
    private function resolveEgcResultsForStudent(int $studentId, int $semesterId, Collection $blocks): array
    {
        $registrations = $this->egcRegistrationsForStudentSemester($studentId, $semesterId);

        $resolvedByBlock = [];

        foreach ($blocks->sortBy('block_number')->values() as $index => $block) {
            $registration = $registrations->get($index);

            if ($registration === null) {
                $resolvedByBlock[(int) $block->id] = $this->resolveEgcBlockResult($studentId, $semesterId, (int) $block->level_number);

                continue;
            }

            $resolvedByBlock[(int) $block->id] = $this->resolveEgcOfferingResult($studentId, $semesterId, (int) $registration->course_offering_id);
        }

        return $resolvedByBlock;
    }

    /**
     * EGC registrations of a student in a semester, ordered so index N pairs with block N+1.
     *
     * @return Collection<int, object{registration_id:int, course_offering_id:int, unit_level:int|null}>
     */
    private function egcRegistrationsForStudentSemester(int $studentId, int $semesterId): Collection
    {
        return DB::table('course_registrations')
            ->join('course_offerings', 'course_registrations.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_registrations.student_id', $studentId)
            ->where('course_offerings.semester_id', $semesterId)
            ->where('units.unit_type', 'egc')
            ->orderBy('course_registrations.id')
            ->select([
                'course_registrations.id as registration_id',
                'course_offerings.id as course_offering_id',
                'units.level as unit_level',
            ])
            ->get()
            ->values();
    }

    /**
     * The EGC level the student is actually registered into for this block, or null when the
     * block has no matching registration yet.
     */
    private function registeredEgcLevelForBlock(EgcBlock $block): ?int
    {
        $registrations = $this->egcRegistrationsForStudentSemester(
            (int) $block->student_id,
            (int) $block->semester_id,
        );

        $blockIndex = EgcBlock::query()
            ->where('student_id', $block->student_id)
            ->where('semester_id', $block->semester_id)
            ->orderBy('block_number')
            ->pluck('id')
            ->search((int) $block->id);

        if ($blockIndex === false) {
            return null;
        }

        $level = $registrations->get($blockIndex)?->unit_level;

        return $level === null ? null : (int) $level;
    }

    /**
     * @return array{result:string, attendance_rate:float|null}|null
     */
    private function resolveEgcOfferingResult(int $studentId, int $semesterId, int $courseOfferingId): ?array
    {
        $records = AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('course_offering_id', $courseOfferingId)
            ->orderByDesc('id')
            ->get();

        return $this->resolveEgcResultFromRecords($records);
    }

    /**
     * @param  Collection<int, AcademicRecord>  $records
     * @return array{result:string, attendance_rate:float|null}|null
     */
    private function resolveEgcResultFromRecords(Collection $records): ?array
    {
        if ($records->isEmpty()) {
            return null;
        }

        $overrideRecord = $records->firstWhere('override_pass', true);
        if ($overrideRecord !== null) {
            return [
                'result' => AcademicEgcBlockData::RESULT_PASS,
                'attendance_rate' => $overrideRecord->attendance_percentage === null ? null : (float) $overrideRecord->attendance_percentage,
            ];
        }

        $latest = $records->first();
        if ($latest->completion_status === 'in_progress') {
            return [
                'result' => AcademicEgcBlockData::RESULT_PENDING,
                'attendance_rate' => null,
            ];
        }

        return [
            'result' => $latest->is_passed ? AcademicEgcBlockData::RESULT_PASS : AcademicEgcBlockData::RESULT_FAIL,
            'attendance_rate' => $latest->attendance_percentage === null ? null : (float) $latest->attendance_percentage,
        ];
    }

    private function immediateNextEgcBlock(EgcBlock $block): ?EgcBlock
    {
        if ((int) $block->block_number === 1) {
            return EgcBlock::query()
                ->where('student_id', $block->student_id)
                ->where('semester_id', $block->semester_id)
                ->where('block_number', 2)
                ->whereKeyNot($block->id)
                ->first();
        }

        if ((int) $block->block_number !== 2) {
            return null;
        }

        $nextSemesterId = $this->nextSemesterIdForEgcBlock($block);
        if ($nextSemesterId === null) {
            return null;
        }

        return EgcBlock::query()
            ->where('student_id', $block->student_id)
            ->where('semester_id', $nextSemesterId)
            ->where('block_number', 1)
            ->whereKeyNot($block->id)
            ->first();
    }
}
