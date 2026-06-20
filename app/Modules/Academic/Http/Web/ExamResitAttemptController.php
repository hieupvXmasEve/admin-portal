<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\Semester;
use App\Modules\Academic\Actions\CancelExamResitAttemptAction;
use App\Modules\Academic\Actions\CompleteExamResitAttemptAction;
use App\Modules\Academic\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Actions\ScheduleExamResitAttemptAction;
use App\Modules\Academic\Http\Requests\ExamResit\CancelExamResitRequest;
use App\Modules\Academic\Http\Requests\ExamResit\CompleteExamResitRequest;
use App\Modules\Academic\Http\Requests\ExamResit\ListExamResitRequest;
use App\Modules\Academic\Http\Requests\ExamResit\ScheduleExamResitRequest;
use App\Modules\Academic\Http\Requests\ExamResit\StoreExamResitRequest;
use App\Modules\Academic\Queries\ListExamResitAttemptsQuery;
use App\Modules\Academic\Queries\ListExamResitEligibleStudentsQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExamResitAttemptController extends Controller
{
    public function __construct(
        private readonly ListExamResitAttemptsQuery $attemptsQuery,
        private readonly ListExamResitEligibleStudentsQuery $eligibilityQuery,
    ) {}

    /**
     * Exam-resit (thi lại) staff worklist.
     */
    public function index(ListExamResitRequest $request): Response
    {
        $validated = $request->validated();
        $filters = array_replace([
            'search' => '',
            'status' => null,
            'operation_state' => null,
            'semester_id' => null,
            'unit_id' => null,
            'sort' => null,
            'direction' => null,
            'per_page' => 15,
        ], $validated);

        $filters['semester_id'] = $filters['semester_id'] !== null ? (int) $filters['semester_id'] : null;
        $filters['unit_id'] = $filters['unit_id'] !== null ? (int) $filters['unit_id'] : null;
        $filters['per_page'] = (int) $filters['per_page'];

        $result = $this->attemptsQuery->handle($filters, session('current_campus_id'));

        return Inertia::render('Academic/ExamResit/Index', [
            'attempts' => $result['attempts'],
            'summary' => $result['summary'],
            'filters' => $filters,
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Show the create form with exam-resit-eligible students/records.
     */
    public function create(ListExamResitRequest $request): Response
    {
        $validated = $request->validated();

        $eligibleStudents = $this->eligibilityQuery->handle([
            'campus_id' => $validated['campus_id'] ?? session('current_campus_id'),
            'semester_id' => $validated['semester_id'] ?? null,
            'search' => $validated['search'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
        ]);

        return Inertia::render('Academic/ExamResit/Create', [
            'eligible_students' => $eligibleStudents->values(),
            'total_eligible' => $eligibleStudents->count(),
            'filters' => $request->only(['search', 'semester_id', 'campus_id', 'unit_id']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
            'campuses' => Campus::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Register (auto-approve) an exam-resit attempt. Academic owns eligibility;
     * HQ owns the fee — this never creates a Finance charge.
     */
    public function store(StoreExamResitRequest $request): RedirectResponse
    {
        app(CreateExamResitAttemptAction::class)->run($request->validated());

        Inertia::flash('success', 'Đăng ký thi lại thành công.');

        return redirect()->route('academic.exam-resit.index');
    }

    /**
     * Show the scheduling form for an approved attempt with the live unit-scoped
     * sessions it can be assigned to.
     */
    public function scheduleForm(ExamResitAttempt $examResit): Response
    {
        $examResit->load(['student:id,student_id,full_name', 'unit:id,code,name']);

        $sessions = ExamResitSession::query()
            ->where('unit_id', $examResit->unit_id)
            ->where('campus_id', $examResit->campus_id)
            ->where('status', ExamResitSession::STATUS_SCHEDULED)
            ->with(['roomSlot:id,room_id,exam_date,start_time,end_time', 'roomSlot.room:id,name,code'])
            ->get()
            ->map(fn (ExamResitSession $session): array => [
                'id' => $session->id,
                'expected_candidates' => (int) $session->expected_candidates,
                'actual_candidates' => (int) $session->actual_candidates,
                'remaining' => max(0, (int) $session->expected_candidates - (int) $session->actual_candidates),
                'exam_date' => $session->roomSlot?->exam_date?->format('Y-m-d'),
                'start_time' => $session->roomSlot?->start_time?->format('H:i'),
                'end_time' => $session->roomSlot?->end_time?->format('H:i'),
                'room' => $session->roomSlot?->room,
            ])
            ->values();

        return Inertia::render('Academic/ExamResit/Schedule', [
            'attempt' => [
                'id' => $examResit->id,
                'student' => $examResit->student,
                'unit' => $examResit->unit,
                'hq_fee_status' => $examResit->hq_fee_status,
                'allow_unpaid_sitting' => (bool) $examResit->allow_unpaid_sitting_snapshot,
            ],
            'sessions' => $sessions,
        ]);
    }

    /**
     * Assign an approved attempt to a unit-scoped session (→ scheduled).
     * Conflict/capacity/payment guards live in the action.
     */
    public function schedule(
        ScheduleExamResitRequest $request,
        ExamResitAttempt $examResit,
        ScheduleExamResitAttemptAction $action,
    ): RedirectResponse {
        $action->run([
            'attempt_id' => $examResit->id,
            'exam_resit_session_id' => (int) $request->validated('exam_resit_session_id'),
            'unpaid_sitting_reason' => $request->validated('unpaid_sitting_reason'),
            'notes' => $request->validated('notes'),
        ]);

        Inertia::flash('success', 'Đã xếp lịch thi lại.');

        return redirect()->route('academic.exam-resit.index');
    }

    /**
     * Show the result-entry form for a scheduled attempt.
     */
    public function completeForm(ExamResitAttempt $examResit): Response
    {
        $examResit->load([
            'student:id,student_id,full_name',
            'unit:id,code,name',
            'academicRecord:id,final_percentage,final_letter_grade',
        ]);

        return Inertia::render('Academic/ExamResit/Complete', [
            'attempt' => [
                'id' => $examResit->id,
                'student' => $examResit->student,
                'unit' => $examResit->unit,
                'hq_fee_status' => $examResit->hq_fee_status,
                'allow_unpaid_sitting' => (bool) $examResit->allow_unpaid_sitting_snapshot,
                'current_final_percentage' => $examResit->academicRecord?->final_percentage,
                'current_letter_grade' => $examResit->academicRecord?->final_letter_grade,
            ],
        ]);
    }

    /**
     * Record an exam-resit sitting result. The higher-score rule + history
     * preservation + GPA-recalc flagging all live in the action.
     */
    public function complete(
        CompleteExamResitRequest $request,
        ExamResitAttempt $examResit,
        CompleteExamResitAttemptAction $action,
    ): RedirectResponse {
        $action->run(array_merge(
            ['attempt_id' => $examResit->id],
            $request->validated(),
        ));

        Inertia::flash('success', 'Đã cập nhật kết quả thi lại.');

        return redirect()->route('academic.exam-resit.index');
    }

    /**
     * Cancel an exam-resit operation before sitting. Paid sources are blocked in
     * the action and must be handled through the HQ refund/reversal flow.
     */
    public function cancel(
        CancelExamResitRequest $request,
        ExamResitAttempt $examResit,
        CancelExamResitAttemptAction $action,
    ): RedirectResponse {
        $action->run([
            'attempt_id' => $examResit->id,
            'reason' => $request->validated('reason'),
        ]);

        Inertia::flash('success', 'Đã hủy thi lại.');

        return back();
    }
}
