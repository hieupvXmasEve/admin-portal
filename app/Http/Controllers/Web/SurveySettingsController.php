<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigService;
use App\Models\Form;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class SurveySettingsController extends Controller
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {}

    /**
     * Display the survey settings page
     */
    public function index(): Response
    {
        $config = $this->systemConfigService->getConfig();
        
        // Get all active survey forms for selection
        $surveyForms = Form::where('type', 'survey')
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'code', 'title', 'description']);

        return Inertia::render('Surveys/Settings', [
            'config' => [
                'survey_enabled' => $config['survey_enabled'] ?? false,
                'default_course_survey' => $config['default_course_survey'] ?? null,
            ],
            'surveyForms' => $surveyForms,
        ]);
    }

    /**
     * Update survey settings
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'survey_enabled' => ['required', 'boolean'],
            'default_course_survey' => ['nullable', 'integer', 'exists:forms,id'],
        ]);

        // If survey is enabled, ensure default form is provided
        if ($validated['survey_enabled'] && empty($validated['default_course_survey'])) {
            return Redirect::back()
                ->with('error', 'Please select a default survey form when survey feature is enabled.');
        }

        // Verify the selected form is active and of type 'survey'
        if (!empty($validated['default_course_survey'])) {
            $form = Form::find($validated['default_course_survey']);
            if (!$form || $form->type !== 'survey' || $form->status !== 'active') {
                return Redirect::back()
                    ->with('error', 'Selected form must be an active survey form.');
            }

            // Check if form has a published version
            $hasPublishedVersion = $form->latestPublishedVersion()->exists();
            if (!$hasPublishedVersion) {
                return Redirect::back()
                    ->with('error', 'Selected form must have at least one published version.');
            }
        }

        try {
            $this->systemConfigService->updateConfig($validated);
            
            return Redirect::back()
                ->with('success', 'Survey settings updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update survey settings: ' . $e->getMessage());
            
            return Redirect::back()
                ->with('error', 'Failed to update survey settings. Please try again.');
        }
    }
}
