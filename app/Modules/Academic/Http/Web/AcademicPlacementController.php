<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Http\Controllers\Controller;
use App\Models\IeltsCertificate;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Actions\Placement\InitializeStudentPlacementAction;
use App\Modules\Academic\Actions\Placement\RecordIeltsCertificateAction;
use App\Modules\Academic\Actions\Placement\TransitionToIntakeCourseAction;
use App\Modules\Academic\Actions\Placement\UpdateStudentEnglishLevelAction;
use App\Modules\Academic\Http\Requests\Placement\InitializePlacementRequest;
use App\Modules\Academic\Http\Requests\Placement\RecordIeltsRequest;
use App\Modules\Academic\Http\Requests\Placement\TransitionToIntakeCourseRequest;
use App\Modules\Academic\Http\Requests\Placement\UpdateEnglishLevelRequest;
use App\Modules\Academic\Queries\Placement\GetAcademicProgressionHistoryQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicPlacementController extends Controller
{
    /**
     * Show the placement & progression tab for a student.
     */
    public function show(Request $request, Student $student, GetAcademicProgressionHistoryQuery $query): Response
    {
        $validated = $request->validate([
            'event_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $progressionEvents = $query->handle($student->id, $validated);

        $student->load(['ieltsCertificates.uploadRecord', 'campus']);

        return Inertia::render('Admin/Students/Placement/Index', [
            'student' => $student,
            'progressionEvents' => $progressionEvents,
            'ieltsCertificates' => $student->ieltsCertificates()
                ->with('uploadRecord')
                ->latestFirst()
                ->get(),
            'latestIelts' => $student->getLatestIeltsCertificate(),
            'canTransitionToIntake' => $student->canTransitionToIntakeCourse(),
            'filters' => [
                'event_type' => $validated['event_type'] ?? null,
                'per_page' => $validated['per_page'] ?? 10,
            ],
            'options' => $this->getFormOptions(),
        ]);
    }

    /**
     * Initialize placement for a student.
     */
    public function initializePlacement(InitializePlacementRequest $request): RedirectResponse
    {
        $student = InitializeStudentPlacementAction::run($request->validatedWithUser());

        return back()->with('success', sprintf(
            'Placement initialized. Student placed into %s.',
            $student->status_label
        ));
    }

    /**
     * Record a new IELTS certificate.
     */
    public function recordIelts(RecordIeltsRequest $request): RedirectResponse
    {
        $certificate = RecordIeltsCertificateAction::run($request->validatedWithUser());

        $message = sprintf('IELTS score %s recorded successfully.', $certificate->overall_score);

        if ($certificate->missing_documents) {
            $message .= ' Note: File scan is missing and needs to be uploaded.';
        }

        return back()->with('success', $message);
    }

    /**
     * Update English level.
     */
    public function updateLevel(UpdateEnglishLevelRequest $request): RedirectResponse
    {
        $student = UpdateStudentEnglishLevelAction::run($request->validatedWithUser());

        return back()->with('success', sprintf(
            'English level updated to %d.',
            $student->gc_current_level
        ));
    }

    /**
     * Transition to intake_course.
     */
    public function transitionToIntake(TransitionToIntakeCourseRequest $request): RedirectResponse
    {
        TransitionToIntakeCourseAction::run($request->validatedWithUser());

        return back()->with('success', 'Student successfully transitioned to Intake Course.');
    }

    /**
     * Upload IELTS document (update missing_documents).
     */
    public function uploadIeltsDocument(Request $request, IeltsCertificate $certificate): RedirectResponse
    {
        $validated = $request->validate([
            'upload_record_id' => ['required', 'integer', 'exists:upload_records,id'],
        ]);

        RecordIeltsCertificateAction::updateDocument($certificate, $validated['upload_record_id']);

        return back()->with('success', 'IELTS document uploaded successfully.');
    }

    /**
     * Get form options for the placement forms.
     */
    protected function getFormOptions(): array
    {
        return [
            'eventTypes' => AcademicProgressionEventType::options(),
            'triggerSources' => ProgressionTriggerSource::options(),
            'semesters' => Semester::query()
                ->select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get(),
            'englishLevels' => [
                ['value' => 0, 'label' => 'Level 0'],
                ['value' => 1, 'label' => 'Level 1'],
                ['value' => 2, 'label' => 'Level 2'],
                ['value' => 3, 'label' => 'Level 3'],
                ['value' => 4, 'label' => 'Level 4'],
                ['value' => 5, 'label' => 'Level 5'],
            ],
            'ieltsScoreThreshold' => IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE,
        ];
    }
}
