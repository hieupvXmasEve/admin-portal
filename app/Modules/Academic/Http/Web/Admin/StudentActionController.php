<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Enums\StudentActionType;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Actions\RecordStudentActionAction;
use App\Modules\Academic\Actions\UpdateStudentActionAction;
use App\Modules\Academic\Actions\UploadActionAttachmentAction;
use App\Modules\Academic\Http\Requests\StoreStudentActionRequest;
use App\Modules\Academic\Http\Requests\UpdateStudentActionRequest;
use App\Modules\Academic\Http\Requests\UploadActionAttachmentRequest;
use App\Modules\Academic\Queries\GetStudentActionHistoryQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentActionController extends Controller
{
    /**
     * Show the action history for a specific student.
     */
    public function index(Request $request, Student $student, GetStudentActionHistoryQuery $query): Response
    {
        $validated = $request->validate([
            'action_type' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $actionLogs = $query->handle($student->id, $validated);

        return Inertia::render('Admin/Students/Actions/Index', [
            'student' => $student->load('campus'),
            'actionLogs' => $actionLogs,
            'filters' => [
                'action_type' => $validated['action_type'] ?? null,
                'per_page' => $validated['per_page'] ?? 10,
            ],
            'options' => $this->getFormOptions(),
        ]);
    }

    /**
     * Store a new student action.
     */
    public function store(StoreStudentActionRequest $request): RedirectResponse
    {
        $actionLog = RecordStudentActionAction::run($request->validatedWithUser());

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
        UpdateStudentActionAction::run($actionLog, $request->validated());

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
     */
    protected function getFormOptions(): array
    {
        return [
            'actionTypes' => StudentActionType::options(),
            'semesters' => Semester::query()
                ->select('id', 'name', 'code', 'start_date', 'end_date')
                ->orderBy('start_date', 'desc')
                ->get(),
            'campuses' => Campus::query()
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
        ];
    }
}
