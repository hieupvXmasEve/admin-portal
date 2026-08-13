<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\Semester;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Delivery\Actions\BulkCreateExamResitAttemptsAction;
use App\Modules\Academic\Delivery\Actions\CancelExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\CompleteExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\ScheduleExamResitAttemptAction;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\CancelExamResitRequest;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\CompleteExamResitRequest;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\ListExamResitRequest;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\ScheduleExamResitRequest;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\StoreExamResitBulkRequest;
use App\Modules\Academic\Delivery\Http\Requests\ExamResit\StoreExamResitRequest;
use App\Modules\Academic\Delivery\Queries\ListExamResitAttemptsQuery;
use App\Modules\Academic\Delivery\Queries\ListExamResitEligibleStudentsQuery;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExamResitAttemptController extends Controller
{
    public function __construct(
        private readonly ListExamResitAttemptsQuery $attemptsQuery,
        private readonly ListExamResitEligibleStudentsQuery $eligibilityQuery,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
        private readonly CampusReferenceReader $campuses,
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
            'semesters' => $this->semesters->options(),
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
            'semesters' => $this->semesters->options(),
            'current_semester_id' => Semester::getActiveSemester()?->id,
            'campuses' => array_map(
                static fn (CampusReference $campus): array => $campus->toArray(),
                $this->campuses->all(),
            ),
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
     * Register exam-resit attempts for multiple records in one submit. Each
     * record runs the same eligibility + catalog-pricing gate as {@see store};
     * one ineligible record does not block the rest of the batch.
     */
    public function storeBulk(StoreExamResitBulkRequest $request, BulkCreateExamResitAttemptsAction $action): RedirectResponse
    {
        $validated = $request->validated();

        $result = $action->handle(
            items: $validated['items'],
            operationSemesterId: (int) $validated['operation_semester_id'],
            chargeSemesterId: (int) $validated['charge_semester_id'],
            notes: $validated['notes'] ?? null,
        );

        Inertia::flash($this->bulkResultFlashKey($result), $this->bulkResultMessage($result));

        return back();
    }

    /**
     * @param  array{succeeded: list<array<string, mixed>>, failed: list<array<string, mixed>>}  $result
     */
    private function bulkResultFlashKey(array $result): string
    {
        if ($result['failed'] === []) {
            return 'success';
        }

        return $result['succeeded'] === [] ? 'error' : 'warning';
    }

    /**
     * @param  array{succeeded: list<array<string, mixed>>, failed: list<array{student_name:?string, reason:string}>}  $result
     */
    private function bulkResultMessage(array $result): string
    {
        $succeededCount = count($result['succeeded']);
        $failedCount = count($result['failed']);

        if ($failedCount === 0) {
            return "Đã đăng ký thi lại thành công cho {$succeededCount} sinh viên.";
        }

        $reasons = collect($result['failed'])
            ->take(5)
            ->map(fn (array $failure): string => ($failure['student_name'] ?? 'Sinh viên').': '.$failure['reason'])
            ->implode('; ');

        $message = "Đăng ký thành công {$succeededCount}, thất bại {$failedCount}. Lý do: {$reasons}";

        return $failedCount > 5 ? $message.'; ...' : $message;
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
     * Cancel an exam-resit operation before sitting. Paid sources are retained
     * without refund when staff explicitly acknowledge the no-refund outcome.
     */
    public function cancel(
        CancelExamResitRequest $request,
        ExamResitAttempt $examResit,
    ): RedirectResponse {
        $validated = $request->validated();

        CancelExamResitAttemptAction::run([
            'attempt_id' => $examResit->id,
            'reason' => $validated['reason'],
            'acknowledge_no_refund' => (bool) ($validated['acknowledge_no_refund'] ?? false),
            'confirmation' => $validated['confirmation'] ?? null,
        ]);

        Inertia::flash('success', 'Đã hủy thi lại.');

        return back();
    }
}
