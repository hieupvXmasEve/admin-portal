<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
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
        $retakeIds = $pageRows->where('exception_type', 'retake_no_charge')->pluck('source_id')->all();
        $deferIds = $pageRows->where('exception_type', 'defer_no_case')->pluck('source_id')->all();

        $missingById = $missingIds === []
            ? collect()
            : CourseRegistration::query()
                ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
                ->whereIn('id', $missingIds)
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

        return $pageRows->map(function (object $row) use ($missingById, $retakeById, $deferById, $semesterId): array {
            return match ($row->exception_type) {
                'missing_charge' => $this->mapMissingChargeRow($missingById[$row->source_id], $semesterId),
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

    private function missingChargeBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return CourseRegistration::query()
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
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

    private function retakeNoChargeBaseQuery(?int $semesterId, ?int $campusId): Builder
    {
        return CourseRegistration::query()
            ->where('is_retake', true)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
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
            'description' => 'Sinh viên đăng ký học nhưng chưa có phí active trong học kỳ',
            'severity' => 'high',
            'context' => [
                'course_registration_id' => $registration->id,
                'semester_id' => $semesterId,
            ],
            'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
            'fixable' => true,
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