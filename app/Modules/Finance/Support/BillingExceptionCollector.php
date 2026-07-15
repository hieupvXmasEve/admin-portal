<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use App\Models\DeferCase;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Finance\Models\FinanceCharge;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BillingExceptionCollector
{
    public function counts(?int $semesterId, ?int $campusId): array
    {
        return [
            'missing_charge' => $this->countMissingCharges($semesterId, $campusId),
            'zero_tuition_waived' => $this->countZeroTuitionWaived($semesterId, $campusId),
            'deferred_enrolled' => $this->countDeferredEnrolled($semesterId, $campusId),
            'retake_no_charge' => $this->countRetakeNoCharges($semesterId, $campusId),
            'defer_no_case' => $this->countDeferNoCases($semesterId, $campusId),
            'mismatch' => 0,
        ];
    }

    public function paginate(
        ?int $semesterId,
        ?string $type,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        if ($type === 'missing_charge') {
            return $this->paginateMissingCharges($semesterId, $campusId, $page, $perPage, $path);
        }

        if ($type === 'zero_tuition_waived') {
            return $this->paginateZeroTuitionWaived($semesterId, $campusId, $page, $perPage, $path);
        }

        if ($type === 'deferred_enrolled') {
            return $this->paginateDeferredEnrolled($semesterId, $campusId, $page, $perPage, $path);
        }

        if ($type === 'retake_no_charge') {
            return $this->paginateRetakeNoCharges($semesterId, $campusId, $page, $perPage, $path);
        }

        if ($type === 'defer_no_case') {
            return $this->paginateDeferNoCases($semesterId, $campusId, $page, $perPage, $path);
        }

        return $this->paginateAllTypes($semesterId, $campusId, $page, $perPage, $path);
    }

    private function countMissingCharges(?int $semesterId, ?int $campusId): int
    {
        return (int) $this->missingChargeBaseQuery($semesterId, $campusId)
            ->distinct()
            ->count('course_registrations.student_id');
    }

    private function countZeroTuitionWaived(?int $semesterId, ?int $campusId): int
    {
        return (int) $this->zeroTuitionWaivedBaseQuery($semesterId, $campusId)
            ->distinct()
            ->count('course_registrations.student_id');
    }

    private function countDeferredEnrolled(?int $semesterId, ?int $campusId): int
    {
        return (int) $this->deferredEnrolledBaseQuery($semesterId, $campusId)
            ->distinct()
            ->count('course_registrations.student_id');
    }

    private function countRetakeNoCharges(?int $semesterId, ?int $campusId): int
    {
        return $this->retakeNoChargeBaseQuery($semesterId, $campusId)->count();
    }

    private function countDeferNoCases(?int $semesterId, ?int $campusId): int
    {
        return $this->deferNoCaseBaseQuery($semesterId, $campusId)->count();
    }

    private function paginateMissingCharges(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $paginator = $this->missingChargeListQuery($semesterId, $campusId)
            ->orderByDesc('course_registrations.created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $registration) => $this->mapMissingChargeRow($registration, $semesterId))
        );
    }

    private function paginateZeroTuitionWaived(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $paginator = $this->zeroTuitionWaivedListQuery($semesterId, $campusId)
            ->orderByDesc('course_registrations.created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $registration) => $this->mapZeroTuitionWaivedRow($registration, $semesterId))
        );
    }

    private function paginateDeferredEnrolled(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $paginator = $this->deferredEnrolledListQuery($semesterId, $campusId)
            ->orderByDesc('course_registrations.created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $registration) => $this->mapDeferredEnrolledRow($registration, $semesterId))
        );
    }

    private function paginateRetakeNoCharges(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $paginator = $this->retakeNoChargeBaseQuery($semesterId, $campusId)
            ->orderByDesc('course_registrations.created_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $registration) => $this->mapRetakeNoChargeRow($registration, $semesterId))
        );
    }

    private function paginateDeferNoCases(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $paginator = $this->deferNoCaseBaseQuery($semesterId, $campusId)
            ->with(['student:id,student_id,full_name'])
            ->orderByDesc('student_action_logs.created_at')
            ->paginate($perPage, ['student_action_logs.*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (StudentActionLog $log) => $this->mapDeferNoCaseRow($log))
        );
    }

    private function paginateAllTypes(
        ?int $semesterId,
        ?int $campusId,
        int $page,
        int $perPage,
        string $path,
    ): LengthAwarePaginator {
        $union = $this->missingChargeUnionQuery($semesterId, $campusId)
            ->unionAll($this->zeroTuitionWaivedUnionQuery($semesterId, $campusId))
            ->unionAll($this->deferredEnrolledUnionQuery($semesterId, $campusId))
            ->unionAll($this->retakeNoChargeUnionQuery($semesterId, $campusId))
            ->unionAll($this->deferNoCaseUnionQuery($semesterId, $campusId));

        $total = (int) DB::query()->fromSub($union, 'billing_exceptions')->count();

        $pageRows = DB::query()
            ->fromSub($union, 'billing_exceptions')
            ->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $items = $this->hydrateUnionRows($pageRows, $semesterId);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'page'],
        );
    }

    private function hydrateUnionRows(Collection $pageRows, ?int $semesterId): Collection
    {
        if ($pageRows->isEmpty()) {
            return collect();
        }

        $missingIds = $pageRows->where('exception_type', 'missing_charge')->pluck('source_id')->all();
        $waivedIds = $pageRows->where('exception_type', 'zero_tuition_waived')->pluck('source_id')->all();
        $deferredIds = $pageRows->where('exception_type', 'deferred_enrolled')->pluck('source_id')->all();
        $retakeIds = $pageRows->where('exception_type', 'retake_no_charge')->pluck('source_id')->all();
        $deferIds = $pageRows->where('exception_type', 'defer_no_case')->pluck('source_id')->all();

        $missingById = $missingIds === []
            ? collect()
            : $this->registrationRowsById($missingIds)
                ->keyBy('id');

        $waivedById = $waivedIds === []
            ? collect()
            : $this->registrationRowsById($waivedIds)
                ->keyBy('id');

        $deferredById = $deferredIds === []
            ? collect()
            : $this->registrationRowsById($deferredIds)
                ->keyBy('id');

        $retakeById = $retakeIds === []
            ? collect()
            : $this->registrationRowsById($retakeIds)
                ->keyBy('id');

        $deferById = $deferIds === []
            ? collect()
            : StudentActionLog::query()
                ->with(['student:id,student_id,full_name'])
                ->whereIn('id', $deferIds)
                ->get()
                ->keyBy('id');

        return $pageRows->map(function (object $row) use ($missingById, $waivedById, $deferredById, $retakeById, $deferById, $semesterId): array {
            return match ($row->exception_type) {
                'missing_charge' => $this->mapMissingChargeRow($missingById[$row->source_id], $semesterId),
                'zero_tuition_waived' => $this->mapZeroTuitionWaivedRow($waivedById[$row->source_id], $semesterId),
                'deferred_enrolled' => $this->mapDeferredEnrolledRow($deferredById[$row->source_id], $semesterId),
                'retake_no_charge' => $this->mapRetakeNoChargeRow($retakeById[$row->source_id], $semesterId),
                'defer_no_case' => $this->mapDeferNoCaseRow($deferById[$row->source_id]),
                default => throw new \RuntimeException("Unknown billing exception type: {$row->exception_type}"),
            };
        })->values();
    }

    private function missingChargeListQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        $representativeIds = $this->missingChargeBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return $this->registrationRowsQuery()
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function missingChargeUnionQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->missingChargeBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'missing_charge' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function zeroTuitionWaivedListQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        $representativeIds = $this->zeroTuitionWaivedBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return $this->registrationRowsQuery()
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function zeroTuitionWaivedUnionQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->zeroTuitionWaivedBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'zero_tuition_waived' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function deferredEnrolledListQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        $representativeIds = $this->deferredEnrolledBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return $this->registrationRowsQuery()
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function deferredEnrolledUnionQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->deferredEnrolledBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'deferred_enrolled' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function retakeNoChargeUnionQuery(?int $semesterId, ?int $campusId): Builder
    {
        return $this->retakeNoChargeBaseQuery($semesterId, $campusId)
            ->selectRaw("'retake_no_charge' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->toBase();
    }

    private function deferNoCaseUnionQuery(?int $semesterId, ?int $campusId): Builder
    {
        return $this->deferNoCaseBaseQuery($semesterId, $campusId)
            ->selectRaw("'defer_no_case' as exception_type, student_action_logs.id as source_id, student_action_logs.created_at")
            ->toBase();
    }

    private function enrolledWithoutActiveChargeBaseQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        return DB::table('course_registrations')
            ->when($campusId, fn ($q) => $q
                ->join('students as billing_exception_students', 'billing_exception_students.id', '=', 'course_registrations.student_id')
                ->where('billing_exception_students.campus_id', $campusId))
            ->when($semesterId, fn ($q) => $q->whereExists(function ($courseOffering) use ($semesterId): void {
                $courseOffering->select(DB::raw(1))
                    ->from('course_offerings')
                    ->whereColumn('course_offerings.id', 'course_registrations.course_offering_id')
                    ->where('course_offerings.semester_id', $semesterId);
            }))
            // FIN-REV-020: a deferred original registration is non-billable, so it
            // must not surface as a missing/zero/deferred enrolled-without-charge exception.
            ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->whereColumn('finance_charges.semester_id', 'course_registrations.semester_id')
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            });
    }

    private function missingChargeBaseQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereNotExists(fn ($query) => BillingExceptionTuitionWaivedMatcher::applyExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function zeroTuitionWaivedBaseQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereExists(fn ($query) => BillingExceptionTuitionWaivedMatcher::applyExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function deferredEnrolledBaseQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function retakeNoChargeBaseQuery(?int $semesterId, ?int $campusId): QueryBuilder
    {
        return $this->registrationRowsQuery()
            ->where('course_registrations.is_retake', true)
            ->when($campusId, fn ($q) => $q->where('students.campus_id', $campusId))
            ->when($semesterId, fn ($q) => $q->where('course_offerings.semester_id', $semesterId))
            // FIN-REV-020: a deferred retake registration is non-billable.
            ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn'])
            ->whereNotExists(fn ($query) => $this->applyLegacyRetakeChargeExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => $this->applyCourseRetakeRegistrationChargeExistsConstraint($query, $semesterId));
    }

    private function applyLegacyRetakeChargeExistsConstraint(Builder $query, ?int $semesterId): void
    {
        $query->select(DB::raw(1))
            ->from('finance_charges')
            ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
            ->whereColumn('finance_charges.semester_id', 'course_registrations.semester_id')
            ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
            ->where('finance_charges.source_type', 'App\\Models\\CourseRegistration')
            ->whereColumn('finance_charges.source_id', 'course_registrations.id');

        if ($semesterId) {
            $query->where('finance_charges.semester_id', $semesterId);
        }
    }

    private function applyCourseRetakeRegistrationChargeExistsConstraint(Builder $query, ?int $semesterId): void
    {
        $query->select(DB::raw(1))
            ->from('course_retake_registrations')
            ->whereColumn('course_retake_registrations.course_registration_id', 'course_registrations.id')
            ->where('course_retake_registrations.status', '!=', 'cancelled')
            ->whereExists(function ($chargeQuery) use ($semesterId): void {
                $chargeQuery->select(DB::raw(1))
                    ->from('finance_charges')
                    ->leftJoin('finance_obligations', 'finance_obligations.id', '=', 'finance_charges.finance_obligation_id')
                    ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
                    ->where(function ($sourceQuery): void {
                        $sourceQuery
                            ->where(function ($canonicalQuery): void {
                                $canonicalQuery
                                    ->where('finance_obligations.source_system', 'academic')
                                    ->where('finance_obligations.source_kind', 'course_retake_registration')
                                    ->whereRaw("finance_obligations.source_ref = CONCAT('retake:', course_retake_registrations.id)");
                            })
                            ->orWhere(function ($legacyQuery): void {
                                $legacyQuery
                                    ->where('finance_charges.source_type', 'App\\Models\\CourseRetakeRegistration')
                                    ->whereColumn('finance_charges.source_id', 'course_retake_registrations.id');
                            });
                    });

                if ($semesterId) {
                    $chargeQuery->where('finance_charges.semester_id', $semesterId);
                }
            });
    }

    private function deferNoCaseBaseQuery(?int $semesterId, ?int $campusId): EloquentBuilder
    {
        return StudentActionLog::query()
            ->where('action_type', StudentActionType::ACADEMIC_DEFER->value)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->where('from_semester_id', $semesterId))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_action_log_id', 'student_action_logs.id');
            });
    }

    private function registrationRowsById(array $ids): Collection
    {
        return $this->registrationRowsQuery()
            ->whereIn('course_registrations.id', array_values(array_unique(array_map('intval', $ids))))
            ->get();
    }

    private function registrationRowsQuery(): QueryBuilder
    {
        return DB::table('course_registrations')
            ->leftJoin('students', 'students.id', '=', 'course_registrations.student_id')
            ->leftJoin('course_offerings', 'course_offerings.id', '=', 'course_registrations.course_offering_id')
            ->leftJoin('units', 'units.id', '=', 'course_offerings.unit_id')
            ->select([
                'course_registrations.id',
                'course_registrations.student_id',
                'course_registrations.semester_id',
                'course_registrations.course_offering_id',
                'course_registrations.registration_status',
                'course_registrations.is_retake',
                'course_registrations.created_at',
                'students.student_id as student_code',
                'students.full_name as student_name',
                'students.status as student_status',
                'students.curriculum_version_id',
                'students.intake_semester_id',
                'students.intake_major',
                'students.campus_id as student_campus_id',
                'course_offerings.semester_id as offering_semester_id',
                'units.name as unit_name',
                'units.code as unit_code',
            ]);
    }

    private function mapMissingChargeRow(object $registration, ?int $semesterId): array
    {
        return [
            'type' => 'missing_charge',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student_code,
            'student_name' => $registration->student_name,
            'description' => 'Sinh viên đăng ký học nhưng chưa có phí học kỳ active cần thu',
            'severity' => 'high',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            'created_at' => $this->createdAtIso($registration->created_at ?? null),
            'fixable' => true,
        ];
    }

    private function mapZeroTuitionWaivedRow(object $registration, ?int $semesterId): array
    {
        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->offering_semester_id;
        $termNumber = null;

        if ($registration->student_id && $resolvedSemesterId) {
            $student = new Student([
                'id' => (int) $registration->student_id,
                'student_id' => $registration->student_code,
                'full_name' => $registration->student_name,
                'curriculum_version_id' => $registration->curriculum_version_id,
                'intake_semester_id' => $registration->intake_semester_id,
                'intake_major' => $registration->intake_major,
            ]);

            $termData = app(StudentChargeTimingResolver::class)
                ->getTuitionTermData($student, (int) $resolvedSemesterId);
            $termNumber = $termData['term_number'];
        }

        return [
            'type' => 'zero_tuition_waived',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student_code,
            'student_name' => $registration->student_name,
            'description' => 'Tuition plan kỳ này có mức phí 0 — không cần sinh charge',
            'severity' => 'low',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                'tuition_term_number' => $termNumber,
            ],
            'created_at' => $this->createdAtIso($registration->created_at ?? null),
            'fixable' => false,
        ];
    }

    private function mapDeferredEnrolledRow(object $registration, ?int $semesterId): array
    {
        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->offering_semester_id;
        $deferCase = $resolvedSemesterId
            ? DeferCase::query()
                ->where('student_id', $registration->student_id)
                ->where('semester_id', $resolvedSemesterId)
                ->first()
            : null;

        $description = match ($deferCase?->fee_policy) {
            DeferCase::POLICY_FORFEIT => 'Sinh viên đã bảo lưu (forfeit phí) nhưng vẫn giữ đăng ký lớp — không cần sinh phí học kỳ',
            DeferCase::POLICY_PRESERVE => 'Sinh viên đã bảo lưu (preserve phí) nhưng vẫn giữ đăng ký lớp — không sinh phí mới học kỳ này',
            DeferCase::POLICY_PARTIAL => 'Sinh viên đã bảo lưu (partial phí) nhưng vẫn giữ đăng ký lớp — kiểm tra defer_case trước khi sinh phí',
            default => 'Sinh viên đã bảo lưu học kỳ này nhưng vẫn giữ đăng ký lớp — không cần sinh phí học kỳ',
        };

        return [
            'type' => 'deferred_enrolled',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student_code,
            'student_name' => $registration->student_name,
            'description' => $description,
            'severity' => 'low',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                'student_status' => $registration->student_status,
                'defer_case_id' => $deferCase?->id,
                'fee_policy' => $deferCase?->fee_policy,
            ],
            'created_at' => $this->createdAtIso($registration->created_at ?? null),
            'fixable' => false,
        ];
    }

    private function mapRetakeNoChargeRow(object $registration, ?int $semesterId): array
    {
        return [
            'type' => 'retake_no_charge',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student_code,
            'student_name' => $registration->student_name,
            'description' => 'Sinh viên học lại nhưng chưa có phí retake active',
            'severity' => 'medium',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            'created_at' => $this->createdAtIso($registration->created_at ?? null),
            'fixable' => true,
        ];
    }

    private function mapDeferNoCaseRow(StudentActionLog $log): array
    {
        return [
            'type' => 'defer_no_case',
            'source_id' => $log->id,
            'student_id' => $log->student_id,
            'student_code' => $log->student?->student_id,
            'student_name' => $log->student?->full_name,
            'description' => 'Defer action ghi nhận nhưng chưa có defer_case',
            'severity' => 'medium',
            'context' => [
                'student_action_log_id' => $log->id,
                'from_semester_id' => $log->from_semester_id,
            ],
            'created_at' => $log->created_at?->toISOString() ?? now()->toISOString(),
            'fixable' => false,
        ];
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
}
