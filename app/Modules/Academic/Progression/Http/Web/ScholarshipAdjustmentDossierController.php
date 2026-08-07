<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\AddCandidateManuallyAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ApproveAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\CompleteInterviewAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\DecideAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\EditMinutesAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\IdentifyCandidatesAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\OverruleDisputeAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\PublishConfirmationRequestNotificationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordOnBehalfConfirmationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RequestConfirmationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ScheduleInterviewAction;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\AddScholarshipAdjustmentCandidateManuallyRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ApproveScholarshipAdjustmentRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\CompleteScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ConfirmScholarshipAdjustmentOnBehalfRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\DecideScholarshipAdjustmentRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\EditScholarshipAdjustmentMinutesRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\IdentifyScholarshipAdjustmentCandidatesRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\OverruleScholarshipAdjustmentDisputeRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\PreviewScholarshipAdjustmentCandidatesRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\PreviewScholarshipAdjustmentImpactRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ScheduleScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Queries\ScholarshipAdjustmentCandidateQuery;
use App\Modules\Academic\Progression\Support\ScholarshipStaffNotificationPublisher;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentPreviewReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScholarshipAdjustmentDossierController extends Controller
{
    /**
     * Dossier list — status, semester, campus filters.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'source_semester_id' => ['nullable', 'integer'],
            'target_semester_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        // Record-level campus scope: view_scholarship_adjustment is granted
        // per-campus, but the route gate (`can:`) resolves at the session
        // campus without filtering rows — a null session campus denies rather
        // than falling through to an all-campus listing.
        $campus = app()->bound('campus') ? app('campus') : null;
        $campusId = $campus?->id ?? session('current_campus_id');

        abort_if($campusId === null, 403, 'Chưa chọn cơ sở.');

        $query = ScholarshipAdjustmentDossier::query()
            ->where('campus_id', $campusId)
            ->with(['student', 'sourceSemester', 'targetSemester', 'campus']);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['source_semester_id'])) {
            $query->where('source_semester_id', $validated['source_semester_id']);
        }

        if (! empty($validated['target_semester_id'])) {
            $query->where('target_semester_id', $validated['target_semester_id']);
        }

        $dossiers = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 20);

        return Inertia::render('ScholarshipAdjustments/Index', [
            'dossiers' => $dossiers,
            'filters' => $validated,
            'campusId' => $campusId,
        ]);
    }

    /**
     * Dossier detail — evidence, needs_data_review banner, interview panel,
     * minutes editor, decision panel.
     */
    public function show(Request $request, ScholarshipAdjustmentDossier $dossier): Response
    {
        $this->authorize('view', $dossier);

        $dossier->load(['student', 'sourceSemester', 'targetSemester', 'campus', 'interviewStaff', 'proposedBy', 'approvedBy']);

        // Money impact of the decision, resolved by Finance so the figures on
        // screen are the ones the ledger would write. A decided dossier previews
        // its own proposed value; an undecided one previews the current position.
        $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview(
            (int) $dossier->student_id,
            (int) $dossier->target_semester_id,
            $dossier->decision_adjusted_amount !== null ? (float) $dossier->decision_adjusted_amount : null,
        );

        return Inertia::render('ScholarshipAdjustments/Show', [
            'dossier' => $dossier,
            'preview' => [...(array) $preview, 'delta' => $preview->delta()],
            // Mirrors the route gates on decide/approve. The decision panel is
            // read-only for a checker who cannot decide, so an approver can no
            // longer overwrite the maker's proposal on the way to approving it.
            'can' => [
                'decide' => $request->user()->can('decide_scholarship_adjustment'),
                'approve' => $request->user()->can('approve_scholarship_adjustment'),
            ],
        ]);
    }

    /**
     * Live money preview while the maker types a value — same reader as show(),
     * so the number they watch is the number that will be charged.
     */
    public function decisionPreview(PreviewScholarshipAdjustmentImpactRequest $request, ScholarshipAdjustmentDossier $dossier): JsonResponse
    {
        $this->authorize('view', $dossier);

        $proposed = $request->validated()['adjusted_amount'] ?? null;

        $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview(
            (int) $dossier->student_id,
            (int) $dossier->target_semester_id,
            $proposed !== null ? (float) $proposed : null,
        );

        return response()->json([...(array) $preview, 'delta' => $preview->delta()]);
    }

    public function identify(IdentifyScholarshipAdjustmentCandidatesRequest $request)
    {
        $validated = $request->validated();

        try {
            $result = app(IdentifyCandidatesAction::class)->run(
                (int) $validated['campus_id'],
                (int) $validated['source_semester_id'],
                (int) $validated['target_semester_id'],
                (int) $request->user()->id,
                isset($validated['student_ids']) ? array_map('intval', $validated['student_ids']) : null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Đã mở đợt xét cho {$result['created']} sinh viên; {$result['skipped_existing']} sinh viên đã được xét từ trước.");
    }

    /**
     * Read-only preview of scan candidates — shows who WOULD get a dossier
     * without writing anything, so staff can pick a subset before creating.
     */
    public function candidatesPreview(PreviewScholarshipAdjustmentCandidatesRequest $request): Response
    {
        $validated = $request->validated();
        $campusId = (int) $validated['campus_id'];

        $preview = null;
        $error = null;

        if (isset($validated['source_semester_id'], $validated['target_semester_id'])) {
            try {
                $result = app(ScholarshipAdjustmentCandidateQuery::class)->handle(
                    $campusId,
                    (int) $validated['source_semester_id'],
                    (int) $validated['target_semester_id'],
                );

                $studentIds = $result['candidates']->pluck('student_id');
                $students = Student::query()->whereIn('id', $studentIds)->get(['id', 'student_id', 'full_name'])->keyBy('id');

                // A candidate who already has a non-cancelled dossier for this
                // exact (student, source, target) pair would just be skipped by
                // IdentifyCandidatesAction — don't show them as pickable here.
                $studentIdsWithDossier = ScholarshipAdjustmentDossier::query()
                    ->whereIn('student_id', $studentIds)
                    ->where('source_semester_id', (int) $validated['source_semester_id'])
                    ->where('target_semester_id', (int) $validated['target_semester_id'])
                    ->where('status', '!=', ScholarshipAdjustmentDossier::STATUS_CANCELLED)
                    ->pluck('student_id');

                $candidatesWithoutDossier = $result['candidates']->reject(
                    fn (array $candidate) => $studentIdsWithDossier->contains($candidate['student_id']),
                );

                $preview = [
                    'excluded_null_is_passed' => $result['excluded_null_is_passed'],
                    'excluded_already_charged' => $result['excluded_already_charged'],
                    'already_has_dossier' => $studentIdsWithDossier->count(),
                    'candidates' => $candidatesWithoutDossier->map(fn (array $candidate) => [
                        'student_id' => $candidate['student_id'],
                        'student' => $students->get($candidate['student_id']),
                        'failed_courses' => $candidate['failed_records']->map(fn ($record) => [
                            'unit_code' => $record->unit?->code,
                            'unit_name' => $record->unit?->name,
                            'grade_finalized_date' => $record->grade_finalized_date,
                        ])->values(),
                        'needs_data_review' => app(ScholarshipAdjustmentCandidateQuery::class)->needsDataReview($candidate['failed_records']),
                    ])->values(),
                ];
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('ScholarshipAdjustments/CandidatesPreview', [
            'campusId' => $campusId,
            'sourceSemesterId' => isset($validated['source_semester_id']) ? (int) $validated['source_semester_id'] : null,
            'targetSemesterId' => isset($validated['target_semester_id']) ? (int) $validated['target_semester_id'] : null,
            'semesters' => Semester::query()->where('is_archived', false)->orderByDesc('start_date')->get(['id', 'code', 'name', 'start_date']),
            'preview' => $preview,
            'error' => $error,
        ]);
    }

    public function addManually(AddScholarshipAdjustmentCandidateManuallyRequest $request)
    {
        $validated = $request->validated();

        try {
            app(AddCandidateManuallyAction::class)->run(
                $validated['student_code'],
                (int) $validated['source_semester_id'],
                (int) $validated['target_semester_id'],
                $validated['exception_reason'],
                (int) $request->user()->id,
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã thêm sinh viên vào danh sách xét.');
    }

    public function scheduleInterview(
        ScheduleScholarshipAdjustmentInterviewRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        $validated = $request->validated();

        ScheduleInterviewAction::run(
            $dossier,
            new \DateTimeImmutable($validated['scheduled_at']),
            $validated['mode'],
            $validated['location'] ?? null,
            (int) $request->user()->id,
        );

        return back()->with('success', 'Đã đặt lịch phỏng vấn.');
    }

    public function completeInterview(
        CompleteScholarshipAdjustmentInterviewRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        $validated = $request->validated();

        $dossier = CompleteInterviewAction::run($dossier, $validated['minutes'], $validated['participants'] ?? []);

        // Completing the interview immediately opens the student-confirmation
        // window (P4) — the student must acknowledge the minutes before a
        // fee-increasing decision.
        $dossier = RequestConfirmationAction::run($dossier);

        app(PublishConfirmationRequestNotificationAction::class)->run($dossier);

        return back()->with('success', 'Đã ghi nhận buổi phỏng vấn — đã gửi yêu cầu xác nhận cho sinh viên.');
    }

    /**
     * Correct the minutes after the interview — the primary way to resolve a
     * student's dispute. Bumps the version, which resets the confirmation to
     * pending so the student answers on the corrected text, and re-notifies.
     */
    public function editMinutes(
        EditScholarshipAdjustmentMinutesRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        try {
            $dossier = EditMinutesAction::run($dossier, $request->validated()['minutes']);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        if ($dossier->confirmation_status === ScholarshipAdjustmentDossier::CONFIRMATION_PENDING) {
            app(PublishConfirmationRequestNotificationAction::class)->run($dossier);
        }

        return back()->with('success', 'Đã cập nhật biên bản — đã gửi sinh viên xác nhận lại bản đã sửa.');
    }

    /**
     * Approver overrules a dispute that survived correction, so a fee decision
     * is not blocked forever. Never recorded as a student confirmation.
     */
    public function overruleDispute(
        OverruleScholarshipAdjustmentDisputeRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        try {
            app(OverruleDisputeAction::class)->run(
                $dossier,
                (int) $request->user()->id,
                $request->validated()['overrule_reason'],
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã bác bỏ phản đối — lý do đã được lưu vào hồ sơ và giờ có thể đề xuất quyết định.');
    }

    public function confirmOnBehalf(
        ConfirmScholarshipAdjustmentOnBehalfRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        try {
            RecordOnBehalfConfirmationAction::run(
                $dossier,
                (int) $request->user()->id,
                $request->validated()['on_behalf_note'],
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã ghi nhận xác nhận thay cho sinh viên.');
    }

    public function decide(
        DecideScholarshipAdjustmentRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        $validated = $request->validated();

        try {
            app(DecideAdjustmentAction::class)->run(
                $dossier,
                $validated['decision_type'],
                isset($validated['adjusted_amount']) ? (float) $validated['adjusted_amount'] : null,
                $validated['reason'],
                (int) $request->user()->id,
                $validated['exception_override_reason'] ?? null,
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        app(ScholarshipStaffNotificationPublisher::class)->readyForDecision($dossier->refresh());

        return back()->with('success', 'Đã lưu quyết định — cần người có quyền duyệt phê duyệt.');
    }

    public function approve(
        ApproveScholarshipAdjustmentRequest $request,
        ScholarshipAdjustmentDossier $dossier,
    ) {
        try {
            $updated = app(ApproveAdjustmentAction::class)->run($dossier, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // Approval can land in several places (fees updated, fees not billed
        // yet, nothing to change, needs finance to look at it) — each one gets
        // a sentence that says what happened, never the stored status value.
        $outcome = match ($updated->status) {
            ScholarshipAdjustmentDossier::STATUS_APPLIED => 'học phí mới đã được áp dụng.',
            ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT => 'học bổng giữ nguyên nên học phí không thay đổi.',
            ScholarshipAdjustmentDossier::STATUS_CANCELLED => 'học bổng đã bị huỷ.',
            ScholarshipAdjustmentDossier::STATUS_NOT_APPLICABLE => 'kỳ áp dụng không thu học phí nên điều chỉnh không có gì để áp dụng.',
            ScholarshipAdjustmentDossier::STATUS_FINANCE_REVIEW_REQUIRED => 'hệ thống chưa cập nhật được học phí — phòng tài chính cần kiểm tra hồ sơ này.',
            default => 'học phí sẽ được cập nhật khi hoá đơn học phí của học kỳ đó được phát hành.',
        };

        return back()->with('success', "Đã duyệt quyết định — {$outcome}");
    }
}
