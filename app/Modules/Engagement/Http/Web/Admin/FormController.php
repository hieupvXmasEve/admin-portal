<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Modules\Engagement\Http\Requests\Forms\CloneFormRequest;
use App\Modules\Engagement\Http\Requests\Forms\CreateFormRequest;
use App\Modules\Engagement\Http\Requests\Forms\CreateTargetRequest;
use App\Modules\Engagement\Http\Requests\Forms\PublishFormRequest;
use App\Modules\Engagement\Http\Requests\Forms\UpdateFormRequest;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormVersion;
use App\Modules\Engagement\Models\QueryTopic;
use App\Modules\Engagement\Support\FormWorkflow;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        protected FormWorkflow $formService,
        private CampusReferenceReader $campuses,
    ) {}

    /**
     * Display a listing of forms.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['type', 'status', 'search', 'campus_id']);
        $forms = $this->formService->getFormsForAdmin($filters);

        // Get filter options
        $campuses = collect($this->campuses->all())->map->toArray();

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
        $campuses = collect($this->campuses->all())->map->toArray();
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
                ->withErrors(['error' => 'Failed to create form: '.$e->getMessage()]);
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
    public function edit(Request $request, Form $form)
    {
        $form->load([
            'versions.sections.questions.options',
            'versions.questions.options',
            'visibilityRoles',
            'resultVisibility',
            'targets',
        ]);

        $roles = Role::select('id', 'code', 'name')->get();
        $campuses = collect($this->campuses->all())->map->toArray();
        $queryTopics = QueryTopic::active()->ordered()->select('id', 'title')->get();

        return Inertia::render('Forms/Admin/Edit', [
            'form' => $form,
            'roles' => $roles,
            'campuses' => $campuses,
            'queryTopics' => $queryTopics,
            'questionTypes' => $this->getQuestionTypes(),
            'visibilityLevels' => $this->getVisibilityLevels(),
            'scopeTypes' => $this->getScopeTypes(),
            'can_configure_aggregate' => (bool) $request->user()?->can('configure_survey_aggregate'),
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
                ->withErrors(['error' => 'Failed to update form: '.$e->getMessage()]);
        }
    }

    /**
     * Publish a form version.
     */
    public function publish(PublishFormRequest $request, Form $form, FormVersion $version)
    {
        abort_unless((int) $version->form_id === (int) $form->id, 404);

        try {
            $publishedVersion = $this->formService->publishVersion($version);

            return back()->with('success', 'Form version published successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to publish form: '.$e->getMessage()]);
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
            return back()->withErrors(['error' => 'Failed to create target: '.$e->getMessage()]);
        }
    }

    /**
     * Clone an existing form.
     */
    public function clone(CloneFormRequest $request, Form $form)
    {
        $validated = $request->validated();
        $code = $validated['new_code'];
        $title = $validated['new_title'];

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

            return back()->withErrors(['error' => 'Failed to clone form: '.$e->getMessage()]);
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
            return back()->withErrors(['error' => 'Failed to delete form: '.$e->getMessage()]);
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
            return back()->withErrors(['error' => 'Failed to archive form: '.$e->getMessage()]);
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
            return back()->withErrors(['error' => 'Failed to restore form: '.$e->getMessage()]);
        }
    }

    /**
     * Activate the specified form.
     */
    public function activate(Form $form)
    {
        try {
            if (! $form->latestPublishedVersion()->exists()) {
                return back()->withErrors(['error' => 'Publish a form version before activating this form.']);
            }

            $form->update(['status' => 'active']);

            return back()->with('success', 'Form activated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to activate form: '.$e->getMessage()]);
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
