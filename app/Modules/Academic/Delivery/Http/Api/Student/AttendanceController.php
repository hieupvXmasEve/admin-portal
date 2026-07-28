<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
// use App\Http\Resources\Api\V1\Student\CourseAttendanceResource;
use App\Modules\Academic\Delivery\Http\Requests\StudentAttendanceRequest;
use App\Modules\Academic\Delivery\Http\Requests\StudentAttendanceSemesterRequest;
use App\Modules\Academic\Delivery\Http\Resources\Api\V1\Student\AttendanceReportResource;
use App\Modules\Academic\Delivery\Queries\GetStudentAttendanceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Get attendance for a specific course
     */
    public function courseAttendance(StudentAttendanceRequest $request, int $courseOfferingId, GetStudentAttendanceQuery $query): JsonResponse
    {
        $studentId = (int) $request->user()->id;

        try {
            $courseAttendance = $query->handle('course', $studentId, $courseOfferingId);

            return ApiResponse::success(
                $courseAttendance,
                [],
                'Course attendance retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Get comprehensive attendance report with semester filtering
     */
    public function report(StudentAttendanceSemesterRequest $request, GetStudentAttendanceQuery $query): JsonResponse
    {
        $studentId = (int) $request->user()->id;
        try {
            $requestedSemesterId = $request->validated('semester_id');
            $report = $query->handle('reportForRequestedPeriod', $studentId, $requestedSemesterId !== null ? (int) $requestedSemesterId : null);
            Log::info('Report Data', ['report_data' => $report['report']]);

            return ApiResponse::success(
                new AttendanceReportResource($report['report']),
                [],
                'Attendance report retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attendance report', [
                'student_id' => $studentId,
                'semester_id' => $report['semester_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve attendance report');
        }
    }
}
