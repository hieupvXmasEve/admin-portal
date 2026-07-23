<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use App\Shared\Contracts\Platform\SystemConfigurationWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class SurveySettingsController extends Controller
{
    public function __construct(
        private SystemConfigurationReader $systemConfiguration,
        private SystemConfigurationWriter $systemConfigurationWriter,
    ) {}

    /**
     * Display the survey settings page
     */
    public function index(): Response
    {
        // Get all active survey forms for selection
        $surveyForms = Form::where('type', 'survey')
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'code', 'title', 'description']);

        return Inertia::render('Surveys/Settings', [
            'config' => [
                'survey_enabled' => $this->systemConfiguration->get('survey_enabled', false),
                'default_course_survey' => $this->systemConfiguration->get('default_course_survey'),
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
        if (! empty($validated['default_course_survey'])) {
            $form = Form::find($validated['default_course_survey']);
            if (! $form || $form->type !== 'survey' || $form->status !== 'active') {
                return Redirect::back()
                    ->with('error', 'Selected form must be an active survey form.');
            }

            // Check if form has a published version
            $hasPublishedVersion = $form->latestPublishedVersion()->exists();
            if (! $hasPublishedVersion) {
                return Redirect::back()
                    ->with('error', 'Selected form must have at least one published version.');
            }
        }

        try {
            $this->systemConfigurationWriter->update($validated);

            return Redirect::back()
                ->with('success', 'Survey settings updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update survey settings: '.$e->getMessage());

            return Redirect::back()
                ->with('error', 'Failed to update survey settings. Please try again.');
        }
    }
}
