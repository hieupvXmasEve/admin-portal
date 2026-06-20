<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Exam-resit (thi lại) staff worklist (ACAD-RET-001 slice 7).
 *
 * Mirrors {@see ListRetakeCourseRegistrationsQuery}: campus/semester/status-scoped,
 * paginated in the database, with derived display badges (payment / schedule /
 * result / operation state) and the staff actions available on each row. Payment
 * state is derived from canonical Finance evidence, never a local manual flag.
 */
class ListExamResitAttemptsQuery
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array{attempts:LengthAwarePaginator,summary:array<string,int>}
     */
    public function handle(array $filters, ?int $campusId): array
    {
        $query = $this->baseQuery($filters, $campusId);

        $summary = $this->buildSummary(clone $query);

        $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc');

        if ($filters['operation_state'] ?? null) {
            $attempts = $this->paginateDerivedState($query, (string) $filters['operation_state'], (int) ($filters['per_page'] ?? 15));
        } else {
            $attempts = $query
                ->paginate($filters['per_page'] ?? 15)
                ->withQueryString()
                ->through(fn (ExamResitAttempt $attempt): array => $this->toRow($attempt));
        }

        return [
            'attempts' => $attempts,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     */
    private function baseQuery(array $filters, ?int $campusId): Builder
    {
        return ExamResitAttempt::query()
            ->with([
                'student:id,student_id,full_name',
                'unit:id,code,name',
                'operationSemester:id,name,code',
                'campus:id,name,code',
                'financeCharge',
                'session:id,exam_room_slot_id,unit_id,status',
                'session.roomSlot:id,room_id,exam_date,start_time,end_time',
                'session.roomSlot.room:id,name,code',
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
            ->when($filters['semester_id'] ?? null, fn (Builder $query, int $id) => $query->where('operation_semester_id', $id))
            ->when($campusId, fn (Builder $query, int $id) => $query->where('campus_id', $id))
            ->when($filters['unit_id'] ?? null, fn (Builder $query, int $id) => $query->where('unit_id', $id));
    }

    private function paginateDerivedState(Builder $query, string $state, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query('page', 1));
        $matchingRows = $query
            ->get()
            ->filter(fn (ExamResitAttempt $attempt): bool => $this->deriveOperationState($attempt)['value'] === $state)
            ->values();

        return new Paginator(
            $matchingRows
                ->forPage($page, $perPage)
                ->map(fn (ExamResitAttempt $attempt): array => $this->toRow($attempt))
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

    /**
     * @return array<string,int>
     */
    private function buildSummary(Builder $query): array
    {
        $summary = [
            'total' => 0,
            'awaiting_payment' => 0,
            'ready_to_schedule' => 0,
            'scheduled' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        $query->get()->each(function (ExamResitAttempt $attempt) use (&$summary): void {
            $operationState = $this->deriveOperationState($attempt)['value'];

            $summary['total']++;
            if (array_key_exists($operationState, $summary)) {
                $summary[$operationState]++;
            }
        });

        return $summary;
    }

    /**
     * @return array<string,mixed>
     */
    private function toRow(ExamResitAttempt $attempt): array
    {
        $session = $attempt->session;
        $slot = $session?->roomSlot;

        return [
            'id' => $attempt->id,
            'student' => $attempt->student,
            'unit' => $attempt->unit,
            'semester' => $attempt->operationSemester,
            'campus' => $attempt->campus,
            'status' => $attempt->status,
            'request_sequence' => $attempt->request_sequence,
            'attempt_number' => $attempt->attempt_number,
            'fee_amount' => $attempt->fee_amount,
            'payment_deadline' => $attempt->payment_deadline,
            'approved_at' => $attempt->approved_at,
            'scheduled_at' => $attempt->scheduled_at,
            'completed_at' => $attempt->completed_at,
            'created_at' => $attempt->created_at,
            'hq_fee_status' => $attempt->hq_fee_status,
            'resit_score' => $attempt->resit_score,
            'final_chosen_score' => $attempt->final_chosen_score,
            'resit_passed' => $attempt->resit_passed,
            'unpaid_allowed_reason' => $attempt->unpaid_allowed_reason,
            'session' => $session ? [
                'id' => $session->id,
                'status' => $session->status,
                'exam_date' => $slot?->exam_date,
                'start_time' => $slot?->start_time?->format('H:i'),
                'end_time' => $slot?->end_time?->format('H:i'),
                'room' => $slot?->room,
            ] : null,
            'payment_state' => $this->derivePaymentState($attempt),
            'schedule_state' => $this->deriveScheduleState($attempt),
            'result_state' => $this->deriveResultState($attempt),
            'operation_state' => $this->deriveOperationState($attempt),
            'available_actions' => $this->deriveAvailableActions($attempt),
        ];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function derivePaymentState(ExamResitAttempt $attempt): array
    {
        if ($attempt->status === ExamResitAttempt::STATUS_CANCELLED) {
            return ['value' => 'cancelled', 'label' => 'Đã hủy', 'variant' => 'destructive'];
        }

        $charge = $attempt->financeCharge;
        if (
            $attempt->hq_fee_status === ExamResitAttempt::HQ_FEE_PAID
            || ($charge && $charge->status === FinanceCharge::STATUS_ACTIVE && $charge->is_fully_paid)
        ) {
            return ['value' => 'paid', 'label' => 'Đã thanh toán', 'variant' => 'success'];
        }

        return ['value' => 'awaiting_payment', 'label' => 'Chờ thanh toán', 'variant' => 'warning'];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function deriveScheduleState(ExamResitAttempt $attempt): array
    {
        return match ($attempt->status) {
            ExamResitAttempt::STATUS_COMPLETED => ['value' => 'completed', 'label' => 'Đã thi', 'variant' => 'success'],
            ExamResitAttempt::STATUS_NO_SHOW => ['value' => 'no_show', 'label' => 'Vắng thi', 'variant' => 'destructive'],
            ExamResitAttempt::STATUS_SCHEDULED => $attempt->exam_resit_session_id
                ? ['value' => 'scheduled', 'label' => 'Đã xếp lịch', 'variant' => 'info']
                : ['value' => 'not_scheduled', 'label' => 'Chưa xếp lịch', 'variant' => 'secondary'],
            default => ['value' => 'not_scheduled', 'label' => 'Chưa xếp lịch', 'variant' => 'secondary'],
        };
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function deriveResultState(ExamResitAttempt $attempt): array
    {
        if ($attempt->status === ExamResitAttempt::STATUS_COMPLETED || $attempt->final_chosen_score !== null || $attempt->resit_score !== null) {
            return ['value' => 'has_result', 'label' => 'Đã có kết quả', 'variant' => 'success'];
        }

        return ['value' => 'no_result', 'label' => 'Chưa có kết quả', 'variant' => 'secondary'];
    }

    /**
     * @return array{value:string,label:string,variant:string}
     */
    private function deriveOperationState(ExamResitAttempt $attempt): array
    {
        return match ($attempt->status) {
            ExamResitAttempt::STATUS_CANCELLED => ['value' => 'cancelled', 'label' => 'Đã hủy', 'variant' => 'destructive'],
            ExamResitAttempt::STATUS_REJECTED => ['value' => 'rejected', 'label' => 'Bị từ chối', 'variant' => 'destructive'],
            ExamResitAttempt::STATUS_REQUESTED => ['value' => 'awaiting_approval', 'label' => 'Chờ duyệt', 'variant' => 'warning'],
            ExamResitAttempt::STATUS_COMPLETED => ['value' => 'completed', 'label' => 'Hoàn tất', 'variant' => 'success'],
            ExamResitAttempt::STATUS_NO_SHOW => ['value' => 'no_show', 'label' => 'Vắng thi', 'variant' => 'destructive'],
            ExamResitAttempt::STATUS_SCHEDULED => ['value' => 'scheduled', 'label' => 'Đã xếp lịch', 'variant' => 'info'],
            default => $this->derivePaymentState($attempt)['value'] === 'paid'
                ? ['value' => 'ready_to_schedule', 'label' => 'Đã thu - chờ xếp lịch', 'variant' => 'purple']
                : ['value' => 'awaiting_payment', 'label' => 'Chờ thanh toán', 'variant' => 'warning'],
        };
    }

    /**
     * @return array<int,string>
     */
    private function deriveAvailableActions(ExamResitAttempt $attempt): array
    {
        $actions = [];

        if ($attempt->status === ExamResitAttempt::STATUS_APPROVED) {
            $actions[] = 'schedule';
        }

        if ($attempt->status === ExamResitAttempt::STATUS_SCHEDULED) {
            $actions[] = 'complete';
        }

        // Cancel is offered only where the action will actually succeed: a paid
        // attempt must go through the HQ refund/reversal flow first.
        if ($attempt->isCancellable() && $attempt->hq_fee_status !== ExamResitAttempt::HQ_FEE_PAID) {
            $actions[] = 'cancel';
        }

        return $actions;
    }
}
