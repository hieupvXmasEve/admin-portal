<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\ExamResitAttempt;
use App\Modules\Finance\Support\ExamResitDngLinkResolver;
use App\Modules\Finance\Support\ExamResitDueClassification;
use App\Modules\Finance\Support\ExamResitDueClassifier;
use App\Modules\Finance\Support\ExamResitDueRowPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Surface overdue exam-resit (thi lại / PTL) sources that still need charge/DNG
 * creation for the Finance Due Reminders worklist (ACAD-RET-002).
 *
 * These rows are visible for triage/handoff but are NOT directly remindable —
 * staff route them to the DNG worklist / Batch Studio DNG flow. The candidate
 * set is DB-scoped (campus + semester + lifecycle + a past sitting/deadline)
 * before the precise per-row overdue clock is derived in PHP, so no full-table
 * materialization happens for routine filtering.
 */
class ListExamResitHandoffQuery
{
    private const PER_PAGE = 25;

    private const PAGE_NAME = 'handoff_page';

    public function __construct(
        private readonly ExamResitDngLinkResolver $linkResolver = new ExamResitDngLinkResolver,
        private readonly ExamResitDueClassifier $classifier = new ExamResitDueClassifier,
    ) {}

    public function handle(?int $semesterId, ?string $search): LengthAwarePaginator
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();
        $now = now();

        $candidates = $this->candidateQuery($campusId, $semesterId, $search, $today, $now)->get();

        $chargeIds = $candidates
            ->pluck('finance_charge_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $withActiveDng = array_flip($this->linkResolver->chargeIdsWithActivePushedDng($chargeIds));

        $rows = $candidates
            ->reject(fn (ExamResitAttempt $attempt) => $attempt->finance_charge_id !== null
                && isset($withActiveDng[(int) $attempt->finance_charge_id]))
            ->map(fn (ExamResitAttempt $attempt) => [
                'attempt' => $attempt,
                'classification' => $this->classifier->classify($attempt, hasActivePushedDng: false, now: $now),
            ])
            ->filter(fn (array $pair) => $this->isOverdueHandoff($pair['classification']))
            ->map(fn (array $pair) => ExamResitDueRowPresenter::handoffRow($pair['attempt'], $pair['classification']))
            ->values();

        return $this->paginate($rows);
    }

    private function candidateQuery(?int $campusId, ?int $semesterId, ?string $search, $today, $now): Builder
    {
        return ExamResitAttempt::query()
            ->with([
                'student:id,student_id,full_name,email,status',
                'unit:id,code,name',
                'financeCharge',
                'session:id,exam_room_slot_id,unit_id,status',
                'session.roomSlot:id,room_id,exam_date,start_time,end_time',
                'session.roomSlot.room:id,name,code',
            ])
            ->when($campusId, fn (Builder $query, int $id) => $query->where('campus_id', $id))
            ->when($semesterId, fn (Builder $query, int $id) => $query->where('operation_semester_id', $id))
            ->whereIn('status', [ExamResitAttempt::STATUS_APPROVED, ExamResitAttempt::STATUS_SCHEDULED])
            ->whereIn('hq_fee_status', [ExamResitAttempt::HQ_FEE_PENDING, ExamResitAttempt::HQ_FEE_CHARGE_CREATED])
            ->where(function (Builder $query) use ($today, $now) {
                $query->where(function (Builder $deadline) use ($now) {
                    $deadline->whereNotNull('payment_deadline')
                        ->where('payment_deadline', '<', $now);
                })->orWhereHas('session.roomSlot', function (Builder $slot) use ($today) {
                    $slot->where('exam_date', '<', $today->toDateString());
                });
            })
            ->when($search, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->whereHas('student', fn (Builder $s) => $s
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn (Builder $u) => $u
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            });
    }

    private function isOverdueHandoff(ExamResitDueClassification $classification): bool
    {
        return $classification->reminderState === ExamResitDueClassification::REMINDER_STATE_NEEDS_CHARGE_OR_DNG
            && ($classification->daysOverdue ?? 0) > 0;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = max(1, (int) request()->get(self::PAGE_NAME, 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => self::PAGE_NAME,
            ],
        );
    }
}
