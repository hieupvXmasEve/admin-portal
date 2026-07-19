<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Actions\UploadActionAttachmentAction;
use App\Modules\Academic\Http\Requests\StoreStudentActionRequest;
use App\Modules\Academic\Http\Requests\UpdateStudentActionRequest;
use App\Modules\Academic\Http\Requests\UploadActionAttachmentRequest;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use App\Modules\Academic\Progression\Actions\UpdateStudentActionAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Support\LifecycleFormOptions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StudentActionController extends Controller
{
    /**
     * Retired standalone Student Actions page.
     *
     * Student Actions are now recorded in place on the Hub's Lifecycle tab
     * (ADR-0009); this route redirects there so old bookmarks keep working.
     */
    public function index(Student $student): RedirectResponse
    {
        return redirect()->route('students.academic-summary.lifecycle', $student->id);
    }

    /**
     * Store a new student action.
     */
    public function store(StoreStudentActionRequest $request): RedirectResponse
    {
        try {
            $actionLog = RecordStudentActionAction::run($request->validatedWithUser());
        } catch (InvalidProgressionState $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Action "%s" recorded successfully.',
            $actionLog->action_type->label()
        ));
    }

    /**
     * Show details of a specific action log.
     */
    public function show(StudentActionLog $actionLog): Response
    {
        $actionLog->load([
            'student.campus',
            'changedBy',
            'fromSemester',
            'returnSemester',
            'intendedIntakeSemester',
            'dropoutSemester',
            'effectiveSemester',
            'fromCampus',
            'toCampus',
            'attachments',
            'decision:id,decision_name,decision_number,decision_signer,issued_at,expires_at,upload_record_id',
            'deferCase:id,student_action_log_id,scope_type',
        ]);

        return Inertia::render('Admin/Students/Actions/Show', [
            'actionLog' => $actionLog,
            'options' => $this->getFormOptions(),
        ]);
    }

    /**
     * Update an existing student action.
     */
    public function update(UpdateStudentActionRequest $request, StudentActionLog $actionLog): RedirectResponse
    {
        UpdateStudentActionAction::run([
            'action_log_id' => (int) $actionLog->id,
            'fields' => $request->validated(),
        ]);

        return back()->with('success', 'Action updated successfully.');
    }

    /**
     * Upload additional attachments to an existing action.
     */
    public function uploadAttachment(
        UploadActionAttachmentRequest $request,
        StudentActionLog $actionLog
    ): RedirectResponse {
        UploadActionAttachmentAction::run($actionLog, $request->validated());

        $message = 'Attachments uploaded successfully.';
        if ($request->boolean('mark_documents_complete')) {
            $message = 'Attachments uploaded and documents marked as complete.';
        }

        return back()->with('success', $message);
    }

    /**
     * Get form options for creating actions.
     *
     * Delegates to the shared {@see LifecycleFormOptions} builder so the Show
     * page and the Hub Lifecycle tab present an identical option shape.
     */
    protected function getFormOptions(?Student $student = null): array
    {
        return app(LifecycleFormOptions::class)->actionOptions($student);
    }
}
