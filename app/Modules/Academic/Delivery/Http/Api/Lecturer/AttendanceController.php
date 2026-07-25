<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSessionResource;
use App\Http\Resources\Api\V1\Lecturer\SessionAttendanceResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Academic\Delivery\Actions\BulkMarkLecturerAttendanceAction;
use App\Modules\Academic\Delivery\Actions\GenerateLecturerAttendanceRecordsAction;
use App\Modules\Academic\Delivery\Actions\MarkLecturerAttendanceAction;
use App\Modules\Academic\Delivery\Queries\GetLecturerAttendanceQuery;
use App\Modules\Academic\Delivery\Queries\GetLecturerAttendanceSummaryQuery;
use App\Modules\Academic\Http\Requests\Delivery\BulkMarkLecturerAttendanceRequest;
use App\Modules\Academic\Http\Requests\Delivery\CourseAttendanceAnalyticsRequest;
use App\Modules\Academic\Http\Requests\Delivery\ExportCourseAttendanceRequest;
use App\Modules\Academic\Http\Requests\Delivery\LecturerAttendanceAlertsRequest;
use App\Modules\Academic\Http\Requests\Delivery\LecturerAttendanceRequest;
use App\Modules\Academic\Http\Requests\Delivery\LecturerAttendanceSummaryRequest;
use App\Modules\Academic\Http\Requests\Delivery\ListLecturerAttendanceRequest;
use App\Modules\Academic\Http\Requests\Delivery\MarkLecturerAttendanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Get attendance sessions overview
     */
    public function index(ListLecturerAttendanceRequest $request, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();
            $sessions = $query->handle('sessions', $lecturer->id, $filters, (int) ($filters['per_page'] ?? 15));

            return ApiResponse::paginated(
                $sessions->through(fn ($session) => new AttendanceSessionResource($session)),
                'Attendance sessions retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance sessions');
        }
    }

    /**
     * Get sessions requiring attention (unmarked attendance)
     */
    public function sessionsRequiringAttention(LecturerAttendanceRequest $request, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $sessions = $query->handle('sessions', $lecturer->id, ['attendance_status' => 'unmarked'], 20);

            return ApiResponse::success(
                AttendanceSessionResource::collection($sessions->items()),
                [],
                'Sessions requiring attention retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve sessions requiring attention');
        }
    }

    /**
     * Get session attendance details
     */
    public function sessionAttendance(LecturerAttendanceRequest $request, int $sessionId, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();
        try {
            $attendanceData = $query->handle('session', $lecturer->id, $sessionId);

            // Log::debug('attendanceData', ['attendanceData' => $attendanceData]);
            if (! $attendanceData) {
                return ApiResponse::success([]);
            }

            return ApiResponse::success(
                new SessionAttendanceResource($attendanceData),
                [],
                'Session attendance retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve session attendance', ['error' => $e->getMessage()]);

            return ApiResponse::serverError('Failed to retrieve session attendance');
        }
    }

    /**
     * Mark attendance for a session
     */
    public function markAttendance(MarkLecturerAttendanceRequest $request, int $sessionId): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $result = MarkLecturerAttendanceAction::run([
                ...$request->validated(),
                'lecturer_id' => $lecturer->id,
                'session_id' => $sessionId,
            ]);

            if ($result['total_errors'] > 0) {
                return ApiResponse::success(
                    $result,
                    [],
                    "Attendance marked with {$result['total_errors']} errors"
                );
            }

            return ApiResponse::success(
                $result,
                [],
                'Attendance marked successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark attendance', ['error' => $e->getMessage()]);
            if ($e->getMessage() === 'Session not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to mark attendance');
        }
    }

    /**
     * Bulk update attendance for multiple sessions
     */
    public function bulkMarkAttendance(BulkMarkLecturerAttendanceRequest $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            return ApiResponse::success(
                BulkMarkLecturerAttendanceAction::run([
                    ...$request->validated(),
                    'lecturer_id' => $lecturer->id,
                ]),
                [],
                'Bulk attendance marking completed'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to process bulk attendance marking');
        }
    }

    /**
     * Get course attendance analytics
     */
    public function courseAnalytics(CourseAttendanceAnalyticsRequest $request, int $courseOfferingId, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $analytics = $query->handle('analytics', $lecturer->id, $courseOfferingId, $request->validated());

            return ApiResponse::success(
                $analytics,
                [],
                'Course attendance analytics retrieved successfully'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Course offering not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to retrieve course attendance analytics');
        }
    }

    /**
     * Get attendance alerts
     */
    public function alerts(LecturerAttendanceAlertsRequest $request, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $alerts = $query->handle('alerts', $lecturer->id, $request->validated());

            return ApiResponse::success(
                $alerts,
                [],
                'Attendance alerts retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance alerts');
        }
    }

    /**
     * Export attendance data
     */
    public function exportAttendance(ExportCourseAttendanceRequest $request, int $courseOfferingId, GetLecturerAttendanceQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $format = $request->validated('format', 'csv');

            $exportData = $query->handle('export', $lecturer->id, $courseOfferingId, $format, $lecturer->full_name);

            return ApiResponse::success(
                $exportData,
                [],
                'Attendance data prepared for export'
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Course offering not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to export attendance data');
        }
    }

    /**
     * Generate attendance records for a session (new endpoint)
     */
    public function generateAttendance(LecturerAttendanceRequest $request, int $session): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $result = GenerateLecturerAttendanceRecordsAction::run(['lecturer_id' => $lecturer->id, 'session_id' => $session]);

            return ApiResponse::success(
                $result,
                [],
                $result['message']
            );
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Session not found or access denied') {
                return ApiResponse::notFound($e->getMessage());
            }

            return ApiResponse::serverError('Failed to generate attendance records');
        }
    }

    /**
     * Get attendance summary for dashboard
     */
    public function summary(LecturerAttendanceSummaryRequest $request, GetLecturerAttendanceSummaryQuery $query): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            return ApiResponse::success(
                $query->handle($lecturer->id, $request->validated()),
                [],
                'Attendance summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance summary');
        }
    }
}
