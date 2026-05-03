<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\AttendanceGridExport;
use App\Exports\CourseOfferingStatisticsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseStatisticsRequest;
use App\Http\Resources\CourseStatisticsResource;
use App\Models\CourseOffering;
use App\Http\Resources\UnitStatisticsResource;
use App\Models\Semester;
use App\Modules\Academic\Queries\GetCourseOfferingScoresQuery;
use App\Services\CourseStatisticsService;
use App\Services\ExcelExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseStatisticsController extends Controller
{
    public function __construct(
        private readonly CourseStatisticsService $service,
        private readonly ExcelExportService $excelService
    ) {}

    public function index(CourseStatisticsRequest $request): Response
    {
        $validated = $request->validated();

        // Default to current active semester if not provided
        if (! isset($validated['semester_id'])) {
            $activeSemester = Semester::getActiveSemester();
            if ($activeSemester) {
                $validated['semester_id'] = $activeSemester->id;
            }
        }

        $statistics = $this->service->getStatistics($validated);

        $semesters = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);

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

    public function show(CourseOffering $courseOffering): Response
    {
        $data = $this->service->getAttendanceGrid($courseOffering->id);
        $data['course_offering'] = [
            'id' => $courseOffering->id,
            'unit_id' => $courseOffering->unit->id,
            'semester_id' => $courseOffering->semester->id,
        ];

        return Inertia::render('CourseStatistics/Detail', $data);
    }

    public function export(CourseOffering $courseOffering): BinaryFileResponse
    {
        $data = $this->service->getAttendanceGrid($courseOffering->id);

        $export = new AttendanceGridExport(
            $data['attendance_grid'],
            $data['sessions']->toArray(),
            $data['statistics']
        );

        $filename = $this->excelService->generateFilenameWithTimestamp(
            "attendance_{$data['statistics']['course_code']}_{$data['statistics']['section_code']}"
        );

        return $this->excelService->download($export, $filename);
    }

    public function assessmentScores(CourseOffering $courseOffering): Response
    {
        $data = GetCourseOfferingScoresQuery::handle($courseOffering);

        return Inertia::render('CourseStatistics/AssessmentScores', $data);
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
