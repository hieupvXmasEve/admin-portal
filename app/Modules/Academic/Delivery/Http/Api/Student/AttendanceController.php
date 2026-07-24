<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\AttendanceReportResource;
// use App\Http\Resources\Api\V1\Student\CourseAttendanceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Academic\Delivery\Queries\GetStudentAttendanceQuery;
use App\Modules\Academic\Http\Requests\Delivery\StudentAttendanceFilterRequest;
use App\Modules\Academic\Http\Requests\Delivery\StudentAttendanceRequest;
use App\Modules\Academic\Http\Requests\Delivery\StudentAttendanceSemesterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Get student's attendance summary
     */
    public function index(StudentAttendanceFilterRequest $request, GetStudentAttendanceQuery $query): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            $filters = $request->validated();
            $semesterId = isset($filters['semester_id']) ? (int) $filters['semester_id'] : null;
            unset($filters['semester_id']);

            $attendance = $query->handle('summary', $student, $semesterId, $filters);

            return ApiResponse::success(
                $attendance,
                [],
                'Attendance summary retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::DEBUG($e->getMessage());

            return ApiResponse::serverError('Failed to retrieve attendance summary');
        }
    }

    /**
     * Get attendance for a specific course
     */
    public function courseAttendance(StudentAttendanceRequest $request, int $courseOfferingId, GetStudentAttendanceQuery $query): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            $courseAttendance = $query->handle('course', $student, $courseOfferingId);

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
     * Get attendance statistics
     */
    public function statistics(StudentAttendanceSemesterRequest $request, GetStudentAttendanceQuery $query): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        try {
            $semesterId = $request->validated('semester_id');
            $statistics = $query->handle('statistics', $student, $semesterId !== null ? (int) $semesterId : null);

            return ApiResponse::success(
                $statistics,
                [],
                'Attendance statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance statistics');
        }
    }

    /**
     * Get comprehensive attendance report with semester filtering
     */
    public function report(StudentAttendanceSemesterRequest $request, GetStudentAttendanceQuery $query): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        try {
            $requestedSemesterId = $request->validated('semester_id');
            $report = $query->handle('reportForRequestedPeriod', $student, $requestedSemesterId !== null ? (int) $requestedSemesterId : null);
            Log::info('Report Data', ['report_data' => $report['report']]);

            return ApiResponse::success(
                new AttendanceReportResource($report['report']),
                [],
                'Attendance report retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attendance report', [
                'student_id' => $student->id,
                'semester_id' => $report['semester_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve attendance report');
        }
    }
}
