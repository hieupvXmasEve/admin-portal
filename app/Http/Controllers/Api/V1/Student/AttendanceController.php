<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\AttendanceFilterRequest;
use App\Http\Resources\Api\V1\Student\AttendanceResource;
use App\Http\Resources\Api\V1\Student\AttendanceReportResource;
//use App\Http\Resources\Api\V1\Student\CourseAttendanceResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\AttendanceService;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Get student's attendance summary
     */
    public function index(AttendanceFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $filters = $request->validated();
            $semesterId = $filters['semester_id'] ?? null;
            unset($filters['semester_id']);

            $attendance = $this->attendanceService->getAttendanceSummary($student, $semesterId, $filters);

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
    public function courseAttendance(Request $request, int $courseOfferingId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $courseAttendance = $this->attendanceService->getCourseAttendance($student, $courseOfferingId);

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
    public function statistics(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $statistics = $this->attendanceService->getAttendanceStatistics($student, $semesterId);

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
    public function report(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;
            $reportData = $this->attendanceService->getAttendanceReport($student, $semesterId);

            return ApiResponse::success(
                new AttendanceReportResource($reportData),
                [],
                'Attendance report retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attendance report', [
                'student_id' => $student->id,
                'semester_id' => $semesterId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve attendance report');
        }
    }
}
