<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\AddScholarshipAdjustmentCandidateManuallyRequest;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\ApproveScholarshipAdjustmentRequest;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\CompleteScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\DecideScholarshipAdjustmentRequest;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\IdentifyScholarshipAdjustmentCandidatesRequest;
use App\Modules\Academic\Http\Requests\ScholarshipAdjustment\ScheduleScholarshipAdjustmentInterviewRequest;
use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Services\ScholarshipAdjustmentCandidateService;
use App\Modules\Academic\Services\ScholarshipAdjustmentDecisionService;
use App\Modules\Academic\Services\ScholarshipAdjustmentInterviewService;
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

    public function identify(IdentifyScholarshipAdjustmentCandidatesRequest $request, ScholarshipAdjustmentCandidateService $service)
    {
        $validated = $request->validated();

        try {
            $result = $service->identify(
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

    public function addManually(AddScholarshipAdjustmentCandidateManuallyRequest $request, ScholarshipAdjustmentCandidateService $service)
    {
        $validated = $request->validated();

        try {
            $service->addManually(
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
        ScholarshipAdjustmentInterviewService $service,
    ) {
        $validated = $request->validated();

        $service->schedule(
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
        ScholarshipAdjustmentInterviewService $service,
    ) {
        $validated = $request->validated();

        $service->complete($dossier, $validated['minutes'], $validated['participants'] ?? []);

        return back()->with('success', 'Interview completed.');
    }

    public function decide(
        DecideScholarshipAdjustmentRequest $request,
        ScholarshipAdjustmentDossier $dossier,
        ScholarshipAdjustmentDecisionService $service,
    ) {
        $validated = $request->validated();

        try {
            $service->decide(
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
        ScholarshipAdjustmentDecisionService $service,
    ) {
        try {
            $updated = $service->approve($dossier, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Decision approved — dossier status: {$updated->status}.");
    }
}
