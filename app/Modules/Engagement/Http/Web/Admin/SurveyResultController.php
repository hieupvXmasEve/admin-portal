<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web\Admin;

use App\Exports\SurveyRunAggregateExport;
use App\Http\Controllers\Controller;
use App\Modules\Engagement\Http\Requests\Forms\SurveyResponseListRequest;
use App\Modules\Engagement\Http\Requests\Forms\SurveyResultIndexRequest;
use App\Modules\Engagement\Http\Requests\Forms\SurveyStatsRequest;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Queries\Surveys\GetSurveyProgramStatsQuery;
use App\Modules\Engagement\Queries\Surveys\GetSurveyResponseListQuery;
use App\Modules\Engagement\Queries\Surveys\GetSurveyRunAggregateQuery;
use App\Modules\Engagement\Queries\Surveys\GetSurveyRunListQuery;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SurveyResultController extends Controller
{
    public function __construct(
        private readonly DepartmentReferenceReader $departments,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * Display a listing of survey runs.
     */
    public function index(SurveyResultIndexRequest $request, GetSurveyRunListQuery $action): Response
    {
        $validated = $request->validated();

        $departments = collect($this->departments->allActive())->map->toArray();

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
            'semesters' => collect($this->academicPeriods->selectable())->map(fn ($period): array => [
                'id' => $period->id,
                'name' => $period->name,
            ]),
            'departments' => $departments,
        ]);
    }

    /**
     * Display aggregate results for a survey run, with prev/next navigation
     * based on the filter context passed via the ?return query param.
     */
    public function aggregate(
        SurveyResponseListRequest $request,
        FormTarget $target,
        GetSurveyRunAggregateQuery $aggregateAction,
        GetSurveyRunListQuery $listAction,
        GetSurveyResponseListQuery $responseAction,
    ): Response {
        $this->assertSurveyTargetIsInCurrentCampus($target);
        $this->authorize('view_survey_results_aggregate');

        // Parse the ?return param (URL-encoded filter querystring from Index)
        $returnParam = $request->query('return', '');
        parse_str($returnParam, $listFilters);

        // Sanitise list filters to prevent injection via ?return
        $allowedListKeys = ['search', 'semester_id', 'department_id', 'status', 'sort', 'direction', 'per_page'];
        $listFilters = array_intersect_key($listFilters, array_flip($allowedListKeys));

        // Validate response filters (for the deferred Responses tab).
        // The Index filters are isolated in the encoded return parameter, so these
        // use the standard useDataTable query keys directly.
        $responseFilters = $request->validated();

        $normalizedResponseFilters = [
            'search' => $responseFilters['search'] ?? null,
            'status' => $responseFilters['status'] ?? 'all',
            'sort' => $responseFilters['sort'] ?? null,
            'direction' => $responseFilters['direction'] ?? null,
            'per_page' => $responseFilters['per_page'] ?? 15,
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
    public function raw(SurveyResponseListRequest $request, FormTarget $target, GetSurveyResponseListQuery $action): Response
    {
        $this->assertSurveyTargetIsInCurrentCampus($target);
        $this->authorize('view_survey_results_raw');

        $validated = $request->validated();

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
        GetSurveyRunAggregateQuery $aggregateAction,
        ExcelExportService $excelService,
    ): BinaryFileResponse {
        $this->assertSurveyTargetIsInCurrentCampus($target);
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
    public function stats(SurveyStatsRequest $request, GetSurveyProgramStatsQuery $query): Response
    {
        $this->authorize('view_survey_results_aggregate');

        $validated = $request->validated();

        $stats = $query->handle($validated);

        return Inertia::render('Forms/Admin/results/Stats', [
            'stats' => $stats,
            'semesters' => collect($this->academicPeriods->selectable())->map(fn ($period): array => [
                'id' => $period->id,
                'name' => $period->name,
            ]),
            'filters' => [
                'semester_id' => $validated['semester_id'] ?? 'all',
            ],
        ]);
    }

    private function assertSurveyTargetIsInCurrentCampus(FormTarget $target): void
    {
        $target->loadMissing('form:id,type');

        abort_unless((int) $target->campus_id === (int) app('campus')->id, 404);
        abort_unless($target->form?->type === 'survey', 404);
    }
}
