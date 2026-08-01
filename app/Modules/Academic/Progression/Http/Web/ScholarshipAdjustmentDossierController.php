<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\AddCandidateManuallyAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ApproveAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\CompleteInterviewAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\DecideAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\IdentifyCandidatesAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordOnBehalfConfirmationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RequestConfirmationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ScheduleInterviewAction;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\AddScholarshipAdjustmentCandidateManuallyRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ApproveScholarshipAdjustmentRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\CompleteScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ConfirmScholarshipAdjustmentOnBehalfRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\DecideScholarshipAdjustmentRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\IdentifyScholarshipAdjustmentCandidatesRequest;
use App\Modules\Academic\Progression\Http\Requests\ScholarshipAdjustment\ScheduleScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
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

        abort_if($campusId === null, 403, 'No campus selected.');

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
        ]);
    }

    /**
     * Dossier detail — evidence, needs_data_review banner, interview panel,
     * minutes editor, decision panel.
     */
    public function show(ScholarshipAdjustmentDossier $dossier): Response
    {
        $this->authorize('view', $dossier);

        $dossier->load(['student', 'sourceSemester', 'targetSemester', 'campus', 'interviewStaff', 'proposedBy', 'approvedBy']);

        return Inertia::render('ScholarshipAdjustments/Show', [
            'dossier' => $dossier,
        ]);
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
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Identified {$result['created']} new candidate(s); {$result['skipped_existing']} already had a dossier.");
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

        return back()->with('success', 'Candidate added manually.');
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

        return back()->with('success', 'Interview scheduled.');
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
        RequestConfirmationAction::run($dossier);

        return back()->with('success', 'Interview completed — student confirmation requested.');
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

        return back()->with('success', 'Confirmation recorded on behalf of the student.');
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

        return back()->with('success', 'Decision recorded — awaiting checker approval.');
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

        return back()->with('success', "Decision approved — dossier status: {$updated->status}.");
    }
}
