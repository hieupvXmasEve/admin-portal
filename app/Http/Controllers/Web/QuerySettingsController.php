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

class QuerySettingsController extends Controller
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {}

    /**
     * Display the query form settings page
     */
    public function index(): Response
    {
        $config = $this->systemConfigService->getConfig();
        
        // Get all active query forms for selection
        $queryForms = Form::where('type', 'query')
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'code', 'title', 'description']);

        return Inertia::render('Forms/Queries/Settings', [
            'config' => [
                'active_query_forms' => $config['active_query_forms'] ?? [],
            ],
            'queryForms' => $queryForms,
        ]);
    }

    /**
     * Update query form settings
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active_query_forms' => ['required', 'array', 'min:1'],
            'active_query_forms.*' => ['integer', 'exists:forms,id'],
        ]);

        // Verify all selected forms are active and of type 'query'
        $formIds = $validated['active_query_forms'];
        $forms = Form::whereIn('id', $formIds)->get();

        if ($forms->count() !== count($formIds)) {
            return Redirect::back()
                ->with('error', 'One or more selected forms do not exist.');
        }

        foreach ($forms as $form) {
            if ($form->type !== 'query') {
                return Redirect::back()
                    ->with('error', "Form '{$form->title}' must be of type 'query'.");
            }

            if ($form->status !== 'active') {
                return Redirect::back()
                    ->with('error', "Form '{$form->title}' must be active.");
            }

            // Check if form has a published version
            $hasPublishedVersion = $form->latestPublishedVersion()->exists();
            if (!$hasPublishedVersion) {
                return Redirect::back()
                    ->with('error', "Form '{$form->title}' must have at least one published version.");
            }
        }

        try {
            $this->systemConfigService->updateConfig([
                'active_query_forms' => $formIds,
            ]);
            
            return Redirect::back()
                ->with('success', 'Query form settings updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update query form settings: ' . $e->getMessage());
            
            return Redirect::back()
                ->with('error', 'Failed to update query form settings. Please try again.');
        }
    }
}

