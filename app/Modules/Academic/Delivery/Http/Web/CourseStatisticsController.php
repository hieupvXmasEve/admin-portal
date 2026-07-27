<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Constants\CourseOfferingRoutes;
use App\Exports\CourseOfferingStatisticsExport;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Delivery\Http\Requests\CourseStatisticsRequest;
use App\Modules\Academic\Delivery\Http\Resources\UnitStatisticsResource;
use App\Services\CourseStatisticsService;
use App\Services\ExcelExportService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseStatisticsController extends Controller
{
    public function __construct(
        private readonly CourseStatisticsService $service,
        private readonly ExcelExportService $excelService,
        private readonly GetSemesterFilterOptionsQuery $semesterFilterOptions,
    ) {}

    public function index(CourseStatisticsRequest $request): Response
    {
        $validated = $request->validated();

        $semesterOptions = $this->semesterFilterOptions->handle();

        // Default to current active semester if not provided
        if (! isset($validated['semester_id']) && $semesterOptions['active_semester_id']) {
            $validated['semester_id'] = $semesterOptions['active_semester_id'];
        }

        $statistics = $this->service->getStatistics($validated);

        $semesters = $semesterOptions['semesters'];

        return Inertia::render('CourseStatistics/Index', [
            'statistics' => [
                'data' => UnitStatisticsResource::collection($statistics->items())->resolve(),
                'current_page' => $statistics->currentPage(),
                'last_page' => $statistics->lastPage(),
                'per_page' => $statistics->perPage(),
                'total' => $statistics->total(),
                'from' => $statistics->firstItem(),
                'to' => $statistics->lastItem(),
                'prev_page_url' => $statistics->previousPageUrl(),
                'next_page_url' => $statistics->nextPageUrl(),
                'links' => $statistics->linkCollection()->toArray(),
            ],
            'semesters' => $semesters,
            'filters' => [
                'semester_id' => $validated['semester_id'] ?? null,
                'search' => $validated['search'] ?? null,
                'per_page' => $validated['per_page'] ?? (int) $statistics->perPage(),
                'sort' => $validated['sort'] ?? 'unit_code',
                'direction' => $validated['direction'] ?? 'asc',
            ],
        ]);
    }

    /**
     * The per-offering assessment-scores page is retired (ADR 0013 phase C);
     * this route only exists so old links/bookmarks land on the Course
     * Offering Cockpit's scores tab, which renders the same scores grid.
     */
    public function assessmentScores(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->route(CourseOfferingRoutes::SHOW, [
            'courseOffering' => $courseOffering,
            'tab' => 'scores',
        ]);
    }

    public function exportCombined(CourseOffering $courseOffering): BinaryFileResponse
    {
        $data = $this->service->getCombinedStatisticsGrid($courseOffering->id);

        $export = new CourseOfferingStatisticsExport(
            $data['attendance_grid'],
            $data['statistics']
        );

        $filename = $this->excelService->generateFilenameWithTimestamp(
            "statistics_{$data['statistics']['course_code']}_{$data['statistics']['section_code']}"
        );

        return $this->excelService->download($export, $filename);
    }
}
