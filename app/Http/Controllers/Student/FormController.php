<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\FormService;
use App\Services\ResponseService;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormResponseResource;
use App\Http\Requests\SubmitFormResponseRequest;
use App\Models\Form;
use App\Models\Campus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        protected FormService $formService,
        protected ResponseService $responseService
    ) {}

    /**
     * Display list of available forms for the student.
     */
    public function index(Request $request)
    {
        $student = auth('student')->user();
        $campus = Campus::findOrFail(session('campus_id'));

        $forms = $this->formService->getAvailableFormsForStudent($student, $campus);

        // Add submission info for each form
        $forms->transform(function ($form) use ($student, $campus) {
            $form->can_submit = $this->formService->canStudentSubmitForm($student, $form, $campus);
            $form->submission_count = $form->responses()
                ->where('submitted_by_student_id', $student->id)
                ->where('campus_id', $campus->id)
                ->count();
            return $form;
        });

        return Inertia::render('Forms/Review/Index', [
            'forms' => FormResource::collection($forms)->resolve($request),
            'campus' => $campus,
        ]);
    }

    /**
     * Display form for submission.
     */
    public function show(Form $form)
    {
        $student = auth('student')->user();
        $campus = Campus::findOrFail(session('campus_id'));

        // Check if student can access this form
        if (!$this->formService->canStudentSubmitForm($student, $form, $campus)) {
            return redirect()->route('student.forms.index')
                ->with('error', 'You cannot submit this form at this time.');
        }

        // Load form with questions and options
        $form->load(['latestPublishedVersion.questions.options', 'latestPublishedVersion.sections']);

        return Inertia::render('Forms/Review/Show', [
            'form' => new FormResource($form),
            'campus' => $campus,
        ]);
    }

    /**
     * Submit form response.
     */
    public function submit(SubmitFormResponseRequest $request, Form $form)
    {
        $student = auth('student')->user();
        $campus = Campus::findOrFail(session('campus_id'));

        // Check if student can submit
        if (!$this->formService->canStudentSubmitForm($student, $form, $campus)) {
            return redirect()->route('student.forms.index')
                ->with('error', 'You cannot submit this form at this time.');
        }

        $version = $form->latestPublishedVersion;
        if (!$version) {
            return redirect()->route('student.forms.index')
                ->with('error', 'This form is not available for submission.');
        }

        try {
            $response = $this->responseService->submitResponse(
                $student,
                $form,
                $version,
                $campus,
                $request->validated()
            );

            return redirect()->route('student.forms.confirmation', $response)
                ->with('success', 'Your response has been submitted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['submit' => 'Failed to submit form: ' . $e->getMessage()]);
        }
    }

    /**
     * Display submission confirmation.
     */
    public function confirmation($responseId)
    {
        $student = auth('student')->user();

        $response = $student->formResponses()
            ->with(['form', 'answers.question'])
            ->findOrFail($responseId);

        return Inertia::render('Review/Forms/Confirmation', [
            'response' => new FormResponseResource($response),
        ]);
    }

    /**
     * Display student's submission history.
     */
    public function history(Request $request)
    {
        $student = auth('student')->user();
        $campus = Campus::findOrFail(session('campus_id'));

        $responses = $student->formResponses()
            ->with(['form', 'campus'])
            ->where('campus_id', $campus->id)
            ->orderBy('submitted_at', 'desc')
            ->paginate(20);

        return Inertia::render('Review/Forms/History', [
            'responses' => FormResponseResource::collection($responses),
            'campus' => $campus,
        ]);
    }

    /**
     * View a submitted response.
     */
    public function viewResponse($responseId)
    {
        $student = auth('student')->user();

        $response = $student->formResponses()
            ->with(['form', 'formVersion.questions.options', 'answers.question', 'answers.selectedOptions'])
            ->findOrFail($responseId);

        return Inertia::render('Review/Forms/ViewResponse', [
            'response' => new FormResponseResource($response),
        ]);
    }
}
