<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Form\CreateFormRequest;
use App\Http\Requests\Form\UpdateFormRequest;
use App\Http\Requests\Form\CreateTargetRequest;
use App\Http\Requests\Form\PublishFormRequest;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Role;
use App\Models\Campus;
use App\Models\QueryTopic;
use App\Services\FormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        protected FormService $formService
    ) {}

    /**
     * Display a listing of forms.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['type', 'status', 'search', 'campus_id']);
        $forms = $this->formService->getFormsForAdmin($filters);

        // Get filter options
        $campuses = Campus::select('id', 'name', 'code')->get();

        return Inertia::render('Forms/Admin/Index', [
            'forms' => $forms,
            'filters' => $filters,
            'campuses' => $campuses,
        ]);
    }

    /**
     * Show the form for creating a new form.
     */
    public function create()
    {
        $roles = Role::select('id', 'code', 'name')->get();
        $campuses = Campus::select('id', 'name', 'code')->get();
        $queryTopics = QueryTopic::active()->ordered()->select('id', 'title')->get();

        return Inertia::render('Forms/Admin/Create', [
            'roles' => $roles,
            'campuses' => $campuses,
            'queryTopics' => $queryTopics,
            'questionTypes' => $this->getQuestionTypes(),
            'visibilityLevels' => $this->getVisibilityLevels(),
            'scopeTypes' => $this->getScopeTypes(),
        ]);
    }

    /**
     * Store a newly created form.
     */
    public function store(CreateFormRequest $request)
    {
        try {
            $form = $this->formService->createCompleteForm($request->validated());

            return redirect()
                ->route('forms.admin.show', $form)
                ->with('success', 'Form created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create form: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified form.
     */
    public function show(Form $form)
    {
        $form->load([
            'creator',
            'versions.sections.questions.options',
            'versions.questions.options',
            'latestPublishedVersion.sections.questions.options',
            'latestPublishedVersion.questions.options',
            'visibilityRoles',
            'resultVisibility.role',
            'targets.campus',
        ]);

        // Get statistics
        $statistics = $this->formService->getFormStatistics($form);

        return Inertia::render('Forms/Admin/Show', [
            'form' => $form,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show the form for editing the specified form.
     */
    public function edit(Form $form)
    {
        $form->load([
            'versions.sections.questions.options',
            'versions.questions.options',
            'visibilityRoles',
            'resultVisibility',
            'targets',
        ]);

        $roles = Role::select('id', 'code', 'name')->get();
        $campuses = Campus::select('id', 'name', 'code')->get();
        $queryTopics = QueryTopic::active()->ordered()->select('id', 'title')->get();

        return Inertia::render('Forms/Admin/Edit', [
            'form' => $form,
            'roles' => $roles,
            'campuses' => $campuses,
            'queryTopics' => $queryTopics,
            'questionTypes' => $this->getQuestionTypes(),
            'visibilityLevels' => $this->getVisibilityLevels(),
            'scopeTypes' => $this->getScopeTypes(),
        ]);
    }

    /**
     * Update the specified form.
     */
    public function update(UpdateFormRequest $request, Form $form)
    {
        try {
            $form = $this->formService->updateFormStructure($form, $request->validated());

            return redirect()
                ->route('forms.admin.show', $form)
                ->with('success', 'Form updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update form: ' . $e->getMessage()]);
        }
    }

    /**
     * Publish a form version.
     */
    public function publish(PublishFormRequest $request, Form $form, FormVersion $version)
    {
        try {
            $publishedVersion = $this->formService->publishVersion($version);

            return back()->with('success', 'Form version published successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to publish form: ' . $e->getMessage()]);
        }
    }

    /**
     * Create a target for the form.
     */
    public function createTarget(CreateTargetRequest $request, Form $form)
    {
        try {
            $target = $this->formService->createTarget($form, $request->validated());

            return back()->with('success', 'Form target created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create target: ' . $e->getMessage()]);
        }
    }

    /**
     * Clone an existing form.
     */
    public function clone(Request $request, Form $form)
    {
        // Check if the request has the expected parameters
        $code = $request->input('new_code') ?: $request->input('code');
        $title = $request->input('new_title') ?: $request->input('title');

        // Validate the parameters
        $request->validate([
            'code' => $code ? [] : ['required', 'string', 'max:100', 'unique:forms,code'],
            'title' => $title ? [] : ['required', 'string', 'max:255'],
            'new_code' => $code ? ['required', 'string', 'max:100', 'unique:forms,code'] : [],
            'new_title' => $title ? ['required', 'string', 'max:255'] : [],
        ]);

        try {
            $clonedForm = $this->formService->cloneForm(
                $form,
                $code,
                $title
            );

            return redirect()
                ->route('forms.admin.show', $clonedForm)
                ->with('success', 'Form cloned successfully.');
        } catch (\Exception $e) {
            \Log::error('Form clone failed', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to clone form: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified form.
     */
    public function destroy(Form $form)
    {
        // Check if form has responses
        if ($form->responses()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete form with existing responses. Archive it instead.']);
        }

        try {
            $this->formService->deleteForm($form);

            return redirect()
                ->route('forms.admin.index')
                ->with('success', 'Form deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete form: ' . $e->getMessage()]);
        }
    }

    /**
     * Archive the specified form.
     */
    public function archive(Form $form)
    {
        try {
            $form->update(['status' => 'archived']);

            return back()->with('success', 'Form archived successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to archive form: ' . $e->getMessage()]);
        }
    }

    /**
     * Restore the specified form.
     */
    public function restore(Form $form)
    {
        try {
            $form->update(['status' => 'draft']);

            return back()->with('success', 'Form restored successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to restore form: ' . $e->getMessage()]);
        }
    }

    /**
     * Activate the specified form.
     */
    public function activate(Form $form)
    {
        try {
            $form->update(['status' => 'active']);
            return back()->with('success', 'Form activated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to activate form: ' . $e->getMessage()]);
        }
    }

    /**
     * Get available question types.
     */
    protected function getQuestionTypes(): array
    {
        return [
            'short_text' => 'Short Text',
            'long_text' => 'Long Text',
            'single_choice' => 'Single Choice',
            'multi_choice' => 'Multiple Choice',
            'rating' => 'Rating',
            'date' => 'Date',
            'number' => 'Number',
            'file' => 'File Upload',
            'yes_no' => 'Yes/No',
        ];
    }

    /**
     * Get visibility levels.
     */
    protected function getVisibilityLevels(): array
    {
        return [
            'own_submission' => 'Own Submission Only',
            'aggregated' => 'Aggregated Data',
            'full_detail' => 'Full Details',
        ];
    }

    /**
     * Get scope types.
     */
    protected function getScopeTypes(): array
    {
        return [
            'global' => 'Global',
            'department' => 'Department',
            'course' => 'Course',
            'section' => 'Section',
            'class_session' => 'Class Session',
        ];
    }
}
