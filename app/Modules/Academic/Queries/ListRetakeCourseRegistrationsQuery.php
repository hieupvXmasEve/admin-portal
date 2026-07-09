<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class ListRetakeCourseRegistrationsQuery
{
    public function __construct(
        private readonly AcademicObligationSettlement $obligationSettlement,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array{registrations:LengthAwarePaginator,summary:array<string,int>}
     */
    public function handle(array $filters, ?int $campusId): array
    {
        $query = $this->baseQuery($filters, $campusId);

        $summary = $this->buildSummary(clone $query);

        $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc');

        if ($filters['operation_state'] ?? null) {
            $registrations = $this->paginateDerivedState($query, (string) $filters['operation_state'], (int) ($filters['per_page'] ?? 15));
        } else {
            $registrations = $query
                ->paginate($filters['per_page'] ?? 15)
                ->withQueryString()
                ->through(fn (CourseRetakeRegistration $registration): array => $this->toRow($registration));
        }

        return [
            'registrations' => $registrations,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function baseQuery(array $filters, ?int $campusId): Builder
    {
        return CourseRetakeRegistration::query()
            ->with([
                'student:id,student_id,full_name',
                'unit:id,code,name',
                'courseOffering:id,section_code,semester_id,campus_id',
                'courseOffering.semester:id,name',
                'courseOffering.campus:id,name',
                'semester:id,name,code',
                'campus:id,name,code',
                'approvedBy:id,name',
                'courseRegistration:id,student_id,course_offering_id,semester_id,registration_status,is_retake,attempt_number,retake_fee,is_retake_paid',
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn (Builder $unitQuery) => $unitQuery
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['semester_id'] ?? null, fn (Builder $query, int $id) => $query->where('semester_id', $id))
            ->when($campusId, fn (Builder $query, int $id) => $query->where('campus_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $query, int $id) => $query->where('unit_id', $id));
    }

    private function paginateDerivedState(Builder $query, string $state, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query('page', 1));
        $matchingRows = $query
            ->get()
            ->filter(fn (CourseRetakeRegistration $registration): bool => $this->deriveOperationState($registration)['value'] === $state)
            ->values();

        return new Paginator(
            $matchingRows
                ->forPage($page, $perPage)
                ->map(fn (CourseRetakeRegistration $registration): array => $this->toRow($registration))
                ->values(),
            $matchingRows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    private function buildSummary(Builder $query): array
    {
        $summary = [
            'total' => 0,
            'awaiting_payment' => 0,
            'paid_waiting_class' => 0,
            'enrolled' => 0,
            'needs_review' => 0,
            'cancelled' => 0,
        ];

        $query->get()->each(function (CourseRetakeRegistration $registration) use (&$summary): void {
            $operationState = $this->deriveOperationState($registration)['value'];

            $summary['total']++;
            if (array_key_exists($operationState, $summary)) {
                $summary[$operationState]++;
            }
        });

        return $summary;
    }

    private function toRow(CourseRetakeRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'student' => $registration->student,
            'unit' => $registration->unit,
            'course_offering' => $registration->courseOffering,
            'semester' => $registration->semester,
            'campus' => $registration->campus,
            'status' => $registration->status,
            'attempt_number' => $registration->attempt_number,
            'retake_fee' => $registration->retake_fee,
            'payment_deadline' => $registration->payment_deadline,
            'approved_at' => $registration->approved_at,
            'created_at' => $registration->created_at,
            'approved_by' => $registration->approvedBy,
            'paid_at' => $registration->paid_at,
            'enrolled_at' => $registration->enrolled_at,
            'course_registration' => $registration->courseRegistration,
            'payment_state' => $this->derivePaymentState($registration),
            'class_state' => $this->deriveClassState($registration),
            'operation_state' => $this->deriveOperationState($registration),
            'available_actions' => $this->deriveAvailableActions($registration),
            'exception_summary' => $this->deriveExceptionSummary($registration),
        ];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function derivePaymentState(CourseRetakeRegistration $registration): array
    {
        if ($registration->status === CourseRetakeRegistration::STATUS_CANCELLED) {
            return ['value' => 'cancelled', 'label' => 'Đã hủy', 'variant' => 'destructive'];
        }

        if ($this->hasPaidEvidence($registration)) {
            return ['value' => 'paid', 'label' => 'Đã thanh toán', 'variant' => 'success'];
        }

        return ['value' => 'awaiting_payment', 'label' => 'Chờ thanh toán', 'variant' => 'warning'];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function deriveClassState(CourseRetakeRegistration $registration): array
    {
        if ($registration->status === CourseRetakeRegistration::STATUS_CANCELLED) {
            return ['value' => 'cancelled', 'label' => 'Không áp dụng', 'variant' => 'secondary'];
        }

        if (! $registration->course_registration_id) {
            return ['value' => 'waiting_staff_class', 'label' => 'Chờ staff thêm lớp', 'variant' => 'purple'];
        }

        $courseRegistration = $registration->courseRegistration;
        if (! $courseRegistration) {
            return ['value' => 'missing_link', 'label' => 'Mất link lớp', 'variant' => 'destructive'];
        }

        $matchesTarget = $courseRegistration->student_id === $registration->student_id
            && (
                $registration->course_offering_id === null
                || $courseRegistration->course_offering_id === $registration->course_offering_id
            );

        if (! $matchesTarget) {
            return ['value' => 'link_mismatch', 'label' => 'Lệch link lớp', 'variant' => 'destructive'];
        }

        if (! in_array($courseRegistration->registration_status, CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES, true)) {
            return ['value' => 'removed', 'label' => 'Đã rời lớp', 'variant' => 'warning'];
        }

        if (! $courseRegistration->is_retake || ! $courseRegistration->is_retake_paid) {
            return ['value' => 'needs_retake_mark', 'label' => 'Cần kiểm tra', 'variant' => 'warning'];
        }

        return ['value' => 'active', 'label' => 'Đang trong lớp', 'variant' => 'success'];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function deriveOperationState(CourseRetakeRegistration $registration): array
    {
        if ($registration->status === CourseRetakeRegistration::STATUS_CANCELLED) {
            return ['value' => 'cancelled', 'label' => 'Đã hủy', 'variant' => 'destructive'];
        }

        $paymentState = $this->derivePaymentState($registration);
        if ($paymentState['value'] !== 'paid') {
            return ['value' => 'awaiting_payment', 'label' => 'Chờ thanh toán', 'variant' => 'warning'];
        }

        $classState = $this->deriveClassState($registration);
        if ($classState['value'] === 'active') {
            return ['value' => 'enrolled', 'label' => 'Đã ghi danh', 'variant' => 'success'];
        }

        if ($classState['value'] === 'waiting_staff_class') {
            return ['value' => 'paid_waiting_class', 'label' => 'Đã thu - chờ thêm lớp', 'variant' => 'purple'];
        }

        return ['value' => 'needs_review', 'label' => 'Cần xử lý', 'variant' => 'destructive'];
    }

    /**
     * @return array<int,string>
     */
    private function deriveAvailableActions(CourseRetakeRegistration $registration): array
    {
        $actions = [];
        $operationState = $this->deriveOperationState($registration)['value'];

        if (in_array($operationState, ['paid_waiting_class', 'needs_review'], true)) {
            $actions[] = 'sync';
        }

        if (in_array($registration->status, CourseRetakeRegistration::CANCELLABLE_STATUSES, true)) {
            $actions[] = 'cancel';
        }

        return $actions;
    }

    private function deriveExceptionSummary(CourseRetakeRegistration $registration): ?string
    {
        $classState = $this->deriveClassState($registration);

        return match ($classState['value']) {
            'missing_link' => 'Registration đã ghi danh nhưng course registration không còn tồn tại.',
            'removed' => 'Sinh viên đã đóng tiền nhưng link lớp hiện tại không còn active.',
            'link_mismatch' => 'Link lớp không khớp sinh viên hoặc lớp học lại.',
            'needs_retake_mark' => 'Course registration đang active nhưng chưa được đánh dấu retake paid.',
            default => null,
        };
    }

    private function hasPaidEvidence(CourseRetakeRegistration $registration): bool
    {
        if (in_array($registration->status, [CourseRetakeRegistration::STATUS_PAID, CourseRetakeRegistration::STATUS_ENROLLED], true)) {
            return true;
        }

        if ($registration->hq_fee_status === CourseRetakeRegistration::HQ_FEE_PAID) {
            return true;
        }

        return $this->obligationSettlement->hasRetakePaidEvidence($registration);
    }
}
