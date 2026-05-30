<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Form\GetSurveyResponseListAction;
use App\Actions\Form\GetSurveyRunAggregateAction;
use App\Actions\Form\GetSurveyRunListAction;
use App\Exports\SurveyRunAggregateExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FormTarget;
use App\Models\Semester;
use App\Queries\Form\GetSurveyProgramStatsQuery;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $departments = Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

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
     * Display aggregate results for a survey run, with prev/next navigation
     * based on the filter context passed via the ?return query param.
     */
    public function aggregate(
        Request $request,
        FormTarget $target,
        GetSurveyRunAggregateAction $aggregateAction,
        GetSurveyRunListAction $listAction,
        GetSurveyResponseListAction $responseAction,
    ): Response {
        $this->authorize('view_survey_results_aggregate');

        // Parse the ?return param (URL-encoded filter querystring from Index)
        $returnParam = $request->query('return', '');
        parse_str($returnParam, $listFilters);

        // Sanitise list filters to prevent injection via ?return
        $allowedListKeys = ['search', 'semester_id', 'department_id', 'status', 'sort', 'direction', 'per_page'];
        $listFilters = array_intersect_key($listFilters, array_flip($allowedListKeys));

        // Validate response filters (for the deferred Responses tab)
        $responseFilters = $request->validate([
            'resp_search' => 'nullable|string|max:255',
            'resp_status' => 'nullable|string|in:submitted,approved,rejected,pending,all',
            'resp_sort' => 'nullable|string|in:submitted_at,status',
            'resp_direction' => 'nullable|string|in:asc,desc',
            'resp_per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Map resp_* params to the action's expected keys
        $normalizedResponseFilters = [
            'search' => $responseFilters['resp_search'] ?? null,
            'status' => $responseFilters['resp_status'] ?? 'all',
            'sort' => $responseFilters['resp_sort'] ?? null,
            'direction' => $responseFilters['resp_direction'] ?? null,
            'per_page' => $responseFilters['resp_per_page'] ?? 15,
        ];

        // Build prev/next navigation from the ordered ID list
        $orderedIds = $listAction->getOrderedIds($listFilters);
        $currentPos = array_search($target->id, $orderedIds);
        $total = count($orderedIds);

        $navigation = [
            'prev_id' => ($currentPos !== false && $currentPos > 0) ? $orderedIds[$currentPos - 1] : null,
            'next_id' => ($currentPos !== false && $currentPos < $total - 1) ? $orderedIds[$currentPos + 1] : null,
            'current_index' => $currentPos !== false ? (int) $currentPos + 1 : null,
            'total' => $total,
            'return_params' => $returnParam,
        ];

        // Eager: cheap header + KPIs (needed to render the page frame immediately)
        $headerData = $aggregateAction->executeHeader($target);

        return Inertia::render('Forms/Admin/results/Aggregate', [
            'target' => $target->load(['form', 'semester', 'formVersion.sections.questions.options']),
            'header' => $headerData['header'],
            'overall' => $headerData['overall'],
            'navigation' => $navigation,
            // Deferred group 'aggregate': sections + per-question chart data (heavy)
            'sections' => Inertia::defer(fn () => $aggregateAction->executeSections($target)['sections'], 'aggregate'),
            // Deferred: only loaded when the Responses tab is first opened
            'responses' => Inertia::defer(fn () => $responseAction->execute(
                $target->load('formVersion.sections.questions.options'),
                $normalizedResponseFilters,
            )),
            'responseFilters' => [
                'search' => $normalizedResponseFilters['search'],
                'status' => $normalizedResponseFilters['status'],
                'sort' => $normalizedResponseFilters['sort'],
                'direction' => $normalizedResponseFilters['direction'],
                'per_page' => $normalizedResponseFilters['per_page'],
            ],
        ]);
    }

    /**
     * Display raw responses for a survey run (standalone deep-link page).
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

    /**
     * Download aggregate results for a survey run.
     */
    public function downloadAggregate(
        FormTarget $target,
        GetSurveyRunAggregateAction $aggregateAction,
        ExcelExportService $excelService,
    ): BinaryFileResponse {
        $this->authorize('view_survey_results_aggregate');

        $headerData = $aggregateAction->executeHeader($target);
        $sectionsData = $aggregateAction->executeSections($target);

        $export = new SurveyRunAggregateExport(
            $headerData['header'],
            $headerData['overall'],
            $sectionsData['sections'],
        );

        $courseInfo = $headerData['header']['course_info'] ?? null;
        $filenamePrefix = $courseInfo
            ? sprintf('survey_results_%s_%s', $courseInfo['code'] ?? 'course', $courseInfo['section'] ?? 'section')
            : sprintf('survey_results_%s', $target->id);

        return $excelService->download(
            $export,
            $excelService->generateFilenameWithTimestamp($filenamePrefix),
        );
    }

    /**
     * Display survey stats aggregated by academic program.
     */
    public function stats(Request $request, GetSurveyProgramStatsQuery $query): Response
    {
        $this->authorize('view_survey_results_aggregate');

        $validated = $request->validate([
            'semester_id' => 'nullable|string',
        ]);

        $stats = $query->handle($validated);

        return Inertia::render('Forms/Admin/results/Stats', [
            'stats' => $stats,
            'semesters' => Semester::orderBy('start_date', 'desc')->get(['id', 'name']),
            'filters' => [
                'semester_id' => $validated['semester_id'] ?? 'all',
            ],
        ]);
    }
}
