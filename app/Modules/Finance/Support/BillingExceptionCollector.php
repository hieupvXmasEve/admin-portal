<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\DeferCase;
use App\Models\StudentActionLog;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Database\Eloquent\Builder;
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
            ->paginate($perPage, ['course_registrations.*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (CourseRegistration $registration) => $this->mapMissingChargeRow($registration, $semesterId))
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
            ->paginate($perPage, ['course_registrations.*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (CourseRegistration $registration) => $this->mapZeroTuitionWaivedRow($registration, $semesterId))
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
            ->paginate($perPage, ['course_registrations.*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (CourseRegistration $registration) => $this->mapDeferredEnrolledRow($registration, $semesterId))
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
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->orderByDesc('course_registrations.created_at')
            ->paginate($perPage, ['course_registrations.*'], 'page', $page)
            ->withPath($path);

        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (CourseRegistration $registration) => $this->mapRetakeNoChargeRow($registration, $semesterId))
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
            : CourseRegistration::query()
                ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
                ->whereIn('id', $missingIds)
                ->get()
                ->keyBy('id');

        $waivedById = $waivedIds === []
            ? collect()
            : CourseRegistration::query()
                ->with([
                    'student:id,student_id,full_name,curriculum_version_id,intake_semester_id,intake_major',
                    'courseOffering.unit',
                ])
                ->whereIn('id', $waivedIds)
                ->get()
                ->keyBy('id');

        $deferredById = $deferredIds === []
            ? collect()
            : CourseRegistration::query()
                ->with(['student:id,student_id,full_name,status', 'courseOffering.unit'])
                ->whereIn('id', $deferredIds)
                ->get()
                ->keyBy('id');

        $retakeById = $retakeIds === []
            ? collect()
            : CourseRegistration::query()
                ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
                ->whereIn('id', $retakeIds)
                ->get()
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

    private function missingChargeListQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->missingChargeBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function missingChargeUnionQuery(?int $semesterId, ?int $campusId): \Illuminate\Database\Query\Builder
    {
        $representativeIds = $this->missingChargeBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'missing_charge' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function zeroTuitionWaivedListQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->zeroTuitionWaivedBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return CourseRegistration::query()
            ->with([
                'student:id,student_id,full_name,curriculum_version_id,intake_semester_id,intake_major',
                'courseOffering.unit',
            ])
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function zeroTuitionWaivedUnionQuery(?int $semesterId, ?int $campusId): \Illuminate\Database\Query\Builder
    {
        $representativeIds = $this->zeroTuitionWaivedBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'zero_tuition_waived' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function deferredEnrolledListQuery(?int $semesterId, ?int $campusId): Builder
    {
        $representativeIds = $this->deferredEnrolledBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name,status', 'courseOffering.unit'])
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function deferredEnrolledUnionQuery(?int $semesterId, ?int $campusId): \Illuminate\Database\Query\Builder
    {
        $representativeIds = $this->deferredEnrolledBaseQuery($semesterId, $campusId)
            ->selectRaw('MIN(course_registrations.id) as id')
            ->groupBy('course_registrations.student_id');

        return DB::table('course_registrations')
            ->selectRaw("'deferred_enrolled' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->whereIn('course_registrations.id', $representativeIds);
    }

    private function retakeNoChargeUnionQuery(?int $semesterId, ?int $campusId): \Illuminate\Database\Query\Builder
    {
        return $this->retakeNoChargeBaseQuery($semesterId, $campusId)
            ->selectRaw("'retake_no_charge' as exception_type, course_registrations.id as source_id, course_registrations.created_at")
            ->toBase();
    }

    private function deferNoCaseUnionQuery(?int $semesterId, ?int $campusId): \Illuminate\Database\Query\Builder
    {
        return $this->deferNoCaseBaseQuery($semesterId, $campusId)
            ->selectRaw("'defer_no_case' as exception_type, student_action_logs.id as source_id, student_action_logs.created_at")
            ->toBase();
    }

    private function enrolledWithoutActiveChargeBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return CourseRegistration::query()
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
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

    private function missingChargeBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereNotExists(fn ($query) => BillingExceptionTuitionWaivedMatcher::applyExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function zeroTuitionWaivedBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereExists(fn ($query) => BillingExceptionTuitionWaivedMatcher::applyExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function deferredEnrolledBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return $this->enrolledWithoutActiveChargeBaseQuery($semesterId, $campusId)
            ->whereExists(fn ($query) => BillingExceptionDeferredEnrollmentMatcher::applyExistsConstraint($query, $semesterId));
    }

    private function retakeNoChargeBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return CourseRegistration::query()
            ->where('is_retake', true)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            // FIN-REV-020: a deferred retake registration is non-billable.
            ->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn'])
            ->whereNotExists(fn ($query) => $this->applyLegacyRetakeChargeExistsConstraint($query, $semesterId))
            ->whereNotExists(fn ($query) => $this->applyCourseRetakeRegistrationChargeExistsConstraint($query, $semesterId));
    }

    private function applyLegacyRetakeChargeExistsConstraint(\Illuminate\Database\Query\Builder $query, ?int $semesterId): void
    {
        $query->select(DB::raw(1))
            ->from('finance_charges')
            ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
            ->whereColumn('finance_charges.semester_id', 'course_registrations.semester_id')
            ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
            ->where('finance_charges.source_type', CourseRegistration::class)
            ->whereColumn('finance_charges.source_id', 'course_registrations.id');

        if ($semesterId) {
            $query->where('finance_charges.semester_id', $semesterId);
        }
    }

    private function applyCourseRetakeRegistrationChargeExistsConstraint(\Illuminate\Database\Query\Builder $query, ?int $semesterId): void
    {
        $query->select(DB::raw(1))
            ->from('course_retake_registrations')
            ->whereColumn('course_retake_registrations.course_registration_id', 'course_registrations.id')
            ->where('course_retake_registrations.status', '!=', CourseRetakeRegistration::STATUS_CANCELLED)
            ->where(function ($linkedChargeQuery) use ($semesterId): void {
                $linkedChargeQuery
                    ->whereExists(function ($chargeQuery) use ($semesterId): void {
                        $chargeQuery->select(DB::raw(1))
                            ->from('finance_charges')
                            ->whereColumn('finance_charges.id', 'course_retake_registrations.finance_charge_id')
                            ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);

                        if ($semesterId) {
                            $chargeQuery->where('finance_charges.semester_id', $semesterId);
                        }
                    })
                    ->orWhereExists(function ($chargeQuery) use ($semesterId): void {
                        $chargeQuery->select(DB::raw(1))
                            ->from('finance_charges')
                            ->where('finance_charges.source_type', CourseRetakeRegistration::class)
                            ->whereColumn('finance_charges.source_id', 'course_retake_registrations.id')
                            ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                            ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);

                        if ($semesterId) {
                            $chargeQuery->where('finance_charges.semester_id', $semesterId);
                        }
                    });
            });
    }

    private function deferNoCaseBaseQuery(?int $semesterId, ?int $campusId): Builder
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

    private function mapMissingChargeRow(CourseRegistration $registration, ?int $semesterId): array
    {
        return [
            'type' => 'missing_charge',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student?->student_id,
            'student_name' => $registration->student?->full_name,
            'description' => 'Sinh viên đăng ký học nhưng chưa có phí học kỳ active cần thu',
            'severity' => 'high',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
            'fixable' => true,
        ];
    }

    private function mapZeroTuitionWaivedRow(CourseRegistration $registration, ?int $semesterId): array
    {
        $registration->loadMissing(['student', 'courseOffering']);

        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->courseOffering?->semester_id;
        $termNumber = null;

        if ($registration->student && $resolvedSemesterId) {
            $termData = app(StudentChargeTimingResolver::class)
                ->getTuitionTermData($registration->student, (int) $resolvedSemesterId);
            $termNumber = $termData['term_number'];
        }

        return [
            'type' => 'zero_tuition_waived',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student?->student_id,
            'student_name' => $registration->student?->full_name,
            'description' => 'Tuition plan kỳ này có mức phí 0 — không cần sinh charge',
            'severity' => 'low',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                'tuition_term_number' => $termNumber,
            ],
            'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
            'fixable' => false,
        ];
    }

    private function mapDeferredEnrolledRow(CourseRegistration $registration, ?int $semesterId): array
    {
        $registration->loadMissing(['student', 'courseOffering']);

        $resolvedSemesterId = $semesterId ?? $registration->semester_id ?? $registration->courseOffering?->semester_id;
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
            'student_code' => $registration->student?->student_id,
            'student_name' => $registration->student?->full_name,
            'description' => $description,
            'severity' => 'low',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $resolvedSemesterId,
                'student_status' => $registration->student?->status,
                'defer_case_id' => $deferCase?->id,
                'fee_policy' => $deferCase?->fee_policy,
            ],
            'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
            'fixable' => false,
        ];
    }

    private function mapRetakeNoChargeRow(CourseRegistration $registration, ?int $semesterId): array
    {
        return [
            'type' => 'retake_no_charge',
            'source_id' => $registration->id,
            'student_id' => $registration->student_id,
            'student_code' => $registration->student?->student_id,
            'student_name' => $registration->student?->full_name,
            'description' => 'Sinh viên học lại nhưng chưa có phí retake active',
            'severity' => 'medium',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
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
}
