<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Form\GetSurveyResponseListAction;
use App\Actions\Form\GetSurveyRunAggregateAction;
use App\Actions\Form\GetSurveyRunListAction;
use App\Http\Controllers\Controller;
use App\Models\FormTarget;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Department;

class SurveyResultController extends Controller
{
    /**
     * Display a listing of survey runs.
     */
    public function index(Request $request, GetSurveyRunListAction $action): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|string',
            'department_id' => 'nullable|string',
            'status' => 'nullable|string|in:active,closed,all',
            'sort' => 'nullable|string|in:created_at,responses_count,status,semester,form_title',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $user = Auth::user();
        $isAdmin = $user->hasSystemRole('admin') || $user->hasSystemRole('super_admin');

        $departments = $isAdmin 
            ? Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])
            : Department::whereHas('memberships', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_active', true);
            })->orderBy('name')->get(['id', 'name', 'code']);

        $runs = $action->execute($validated);

        return Inertia::render('Forms/Admin/results/Index', [
            'runs' => $runs,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'semester_id' => $validated['semester_id'] ?? 'all',
                'department_id' => $validated['department_id'] ?? 'all',
                'status' => $validated['status'] ?? 'all',
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'semesters' => Semester::orderBy('start_date', 'desc')->get(['id', 'name']),
            'departments' => $departments,
        ]);
    }

    /**
     * Display aggregate results for a survey run.
     */
    public function aggregate(FormTarget $target, GetSurveyRunAggregateAction $action): Response
    {
        $this->authorize('view_survey_results_aggregate');

        $data = $action->execute($target);

        return Inertia::render('Forms/Admin/results/Aggregate', [
            'target' => $target->load(['form', 'semester']),
            'data' => $data,
        ]);
    }

    /**
     * Display raw responses for a survey run.
     */
    public function raw(Request $request, FormTarget $target, GetSurveyResponseListAction $action): Response
    {
        $this->authorize('view_survey_results_raw');

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:submitted,approved,rejected,pending,all',
            'sort' => 'nullable|string|in:submitted_at,status',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $responses = $action->execute($target, $validated);

        return Inertia::render('Forms/Admin/results/Raw', [
            'target' => $target->load(['form', 'semester', 'formVersion.sections.questions.options']),
            'responses' => $responses,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'status' => $validated['status'] ?? 'all',
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
        ]);
    }
}
