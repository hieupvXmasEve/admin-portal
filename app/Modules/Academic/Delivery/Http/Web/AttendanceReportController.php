<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Exports\AttendanceGridExport;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingAttendanceReportQuery;
use App\Services\ExcelExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly GetCourseOfferingAttendanceReportQuery $attendanceReport,
        private readonly ExcelExportService $excelService,
    ) {}

    public function show(CourseOffering $courseOffering): Response
    {
        $data = $this->attendanceReport->handle($courseOffering);
        $data['course_offering'] = [
            'id' => $courseOffering->id,
            'unit_id' => $courseOffering->unit->id,
            'semester_id' => $courseOffering->semester->id,
        ];

        return Inertia::render('CourseStatistics/Detail', $data);
    }

    public function export(CourseOffering $courseOffering): BinaryFileResponse
    {
        $data = $this->attendanceReport->handle($courseOffering);
        $export = new AttendanceGridExport(
            $data['attendance_grid'],
            $data['sessions']->toArray(),
            $data['statistics'],
        );
        $filename = $this->excelService->generateFilenameWithTimestamp(
            "attendance_{$data['statistics']['course_code']}_{$data['statistics']['section_code']}",
        );

        return $this->excelService->download($export, $filename);
    }
}
