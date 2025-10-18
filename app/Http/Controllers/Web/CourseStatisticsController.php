<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\AttendanceGridExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseStatisticsRequest;
use App\Http\Resources\CourseStatisticsResource;
use App\Models\CourseOffering;
use App\Models\Semester;
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
        $filters = $request->validated();

        $statistics = $this->service->getStatistics($filters);

        // Post-process each item to recalculate students_absent_exceeded
        $items = $statistics->items();
        foreach ($items as $item) {
            // Get total sessions for this course offering
            $totalSessions = $item->classSessions()->count();
            $allowedAbsences = (int) ceil($totalSessions * 0.2);

            // Recalculate students who exceeded based on actual absences count
            $studentsExceeded = $item->academicRecords()
                ->where('total_absences', '>', $allowedAbsences)
                ->count();

            $item->students_absent_exceeded = $studentsExceeded;
            $item->total_sessions = $totalSessions;
            $item->allowed_absences = $allowedAbsences;
        }

        $semesters = Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);

        $paginatedData = [
            'data' => CourseStatisticsResource::collection($items)->resolve(),
            'current_page' => $statistics->currentPage(),
            'last_page' => $statistics->lastPage(),
            'per_page' => $statistics->perPage(),
            'total' => $statistics->total(),
            'from' => $statistics->firstItem(),
            'to' => $statistics->lastItem(),
            'prev_page_url' => $statistics->previousPageUrl(),
            'next_page_url' => $statistics->nextPageUrl(),
            'links' => $statistics->linkCollection()->toArray(),
        ];

        return Inertia::render('CourseStatistics/Index', [
            'statistics' => $paginatedData,
            'semesters' => $semesters,
            'filters' => $filters,
        ]);
    }

    public function show(CourseOffering $courseOffering): Response
    {
        $data = $this->service->getAttendanceGrid($courseOffering->id);
        $data['course_offering'] = ['id' => $courseOffering->id];

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
}
