<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Modules\Academic\Http\Requests\Placement\InitializePlacementRequest;
use App\Modules\Academic\Http\Requests\Placement\RecordIeltsRequest;
use App\Modules\Academic\Http\Requests\Placement\TransitionToIntakeCourseRequest;
use App\Modules\Academic\Http\Requests\Placement\UpdateEnglishLevelRequest;
use App\Modules\Academic\Progression\Actions\Placement\InitializeStudentPlacementAction;
use App\Modules\Academic\Progression\Actions\Placement\RecordIeltsCertificateAction;
use App\Modules\Academic\Progression\Actions\Placement\TransitionToIntakeCourseAction;
use App\Modules\Academic\Progression\Actions\Placement\UpdateStudentEnglishLevelAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AcademicPlacementController extends Controller
{
    /**
     * Retired standalone EGC Placement & Progression page.
     *
     * EGC placement and progression are now managed in place on the Hub's
     * Lifecycle tab (ADR-0009); this route redirects there so old bookmarks
     * keep working.
     */
    public function show(Student $student): RedirectResponse
    {
        return redirect()->route('students.academic-summary.lifecycle', $student->id);
    }

    /**
     * Initialize placement for a student.
     */
    public function initializePlacement(InitializePlacementRequest $request): RedirectResponse
    {
        try {
            $student = InitializeStudentPlacementAction::run($request->validatedWithUser());
        } catch (InvalidProgressionState $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

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
        try {
            $student = UpdateStudentEnglishLevelAction::run($request->validatedWithUser());
        } catch (InvalidProgressionState $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

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
        try {
            TransitionToIntakeCourseAction::run($request->validatedWithUser());
        } catch (InvalidProgressionState $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

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
}
