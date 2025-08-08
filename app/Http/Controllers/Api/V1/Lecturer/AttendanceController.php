<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\AttendanceFilterRequest;
use App\Http\Requests\Api\V1\Lecturer\MarkAttendanceRequest;
use App\Http\Resources\Api\V1\Lecturer\AttendanceSessionResource;
use App\Http\Resources\Api\V1\Lecturer\SessionAttendanceResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Lecturer\LecturerAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    public function __construct(
        protected LecturerAttendanceService $attendanceService
    ) {}

    /**
     * Get attendance sessions overview
     */
    public function index(AttendanceFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();
            $perPage = (int) $request->query('per_page', 15);
            $perPage = min(max($perPage, 5), 50);

            $sessions = $this->attendanceService->getAttendanceSessions($lecturer, $filters, $perPage);

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
    public function sessionsRequiringAttention(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = ['attendance_status' => 'unmarked'];
            $sessions = $this->attendanceService->getAttendanceSessions($lecturer, $filters, 20);

            return ApiResponse::success(
                AttendanceSessionResource::collection($sessions->items()),
                'Sessions requiring attention retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve sessions requiring attention');
        }
    }

    /**
     * Get session attendance details
     */
    public function sessionAttendance(Request $request, int $sessionId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        try {
            $attendanceData = $this->attendanceService->getSessionAttendance($lecturer, $sessionId);

            // Log::debug('attendanceData', ['attendanceData' => $attendanceData]);
            if (! $attendanceData) {
                return ApiResponse::success([]);
            }

            return ApiResponse::success(
                new SessionAttendanceResource($attendanceData),
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
    public function markAttendance(MarkAttendanceRequest $request, int $sessionId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            Log::debug('request', ['request' => $request->all()]);
            $attendanceData = $request->validated()['attendance_data'];

            $result = $this->attendanceService->markAttendance($lecturer, $sessionId, $attendanceData);

            Log::debug('result', ['result' => $result]);

            if ($result['total_errors'] > 0) {
                return ApiResponse::success(
                    $result,
                    "Attendance marked with {$result['total_errors']} errors"
                );
            }

            return ApiResponse::success(
                $result,
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
    public function bulkMarkAttendance(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $validated = $request->validate([
                'sessions' => 'required|array|min:1|max:10',
                'sessions.*.session_id' => 'required|integer',
                'sessions.*.attendance_data' => 'required|array',
                'sessions.*.attendance_data.*.student_code' => 'required|integer',
                'sessions.*.attendance_data.*.status' => 'required|in:present,absent,late,excused',
            ]);

            $results = [];
            $totalProcessed = 0;
            $totalErrors = 0;

            foreach ($validated['sessions'] as $sessionData) {
                try {
                    $result = $this->attendanceService->markAttendance(
                        $lecturer,
                        $sessionData['session_id'],
                        $sessionData['attendance_data']
                    );

                    $results[] = $result;
                    $totalProcessed += $result['total_marked'];
                    $totalErrors += $result['total_errors'];
                } catch (\Exception $e) {
                    $results[] = [
                        'session_id' => $sessionData['session_id'],
                        'error' => $e->getMessage(),
                    ];
                    $totalErrors++;
                }
            }

            return ApiResponse::success([
                'total_sessions_processed' => count($validated['sessions']),
                'total_attendance_marked' => $totalProcessed,
                'total_errors' => $totalErrors,
                'results' => $results,
            ], 'Bulk attendance marking completed');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to process bulk attendance marking');
        }
    }

    /**
     * Get course attendance analytics
     */
    public function courseAnalytics(Request $request, int $courseOfferingId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['date_from', 'date_to', 'student_code']);

            $analytics = $this->attendanceService->getCourseAttendanceAnalytics(
                $lecturer,
                $courseOfferingId,
                $filters
            );

            return ApiResponse::success(
                $analytics,
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
    public function alerts(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->only(['priority', 'type', 'course_offering_id']);

            $alerts = $this->attendanceService->getAttendanceAlerts($lecturer, $filters);

            return ApiResponse::success(
                $alerts,
                'Attendance alerts retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance alerts');
        }
    }

    /**
     * Export attendance data
     */
    public function exportAttendance(Request $request, int $courseOfferingId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $format = $request->query('format', 'csv');

            if (! in_array($format, ['csv', 'excel', 'pdf'])) {
                return ApiResponse::validationError(
                    ['format' => ['Format must be csv, excel, or pdf']],
                    'Invalid export format'
                );
            }

            $exportData = $this->attendanceService->exportAttendanceData(
                $lecturer,
                $courseOfferingId,
                $format
            );

            return ApiResponse::success(
                $exportData,
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
    public function generateAttendance(Request $request, int $sessionId): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $result = $this->attendanceService->generateAttendanceRecords($lecturer, $sessionId);

            return ApiResponse::success(
                $result,
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
    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $filters = $semesterId ? ['semester_id' => $semesterId] : [];

            // Get recent sessions for summary
            $sessions = $this->attendanceService->getAttendanceSessions($lecturer, $filters, 50);

            $totalSessions = $sessions->total();
            $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();
            $pendingSessions = $sessions->where('attendance_marked', false)
                ->where('session_date', '<', now()->subHours(2))
                ->count();

            $summary = [
                'total_sessions' => $totalSessions,
                'sessions_with_attendance' => $sessionsWithAttendance,
                'pending_sessions' => $pendingSessions,
                'completion_rate' => $totalSessions > 0
                    ? round(($sessionsWithAttendance / $totalSessions) * 100, 1)
                    : 0,
                'recent_sessions' => $sessions->take(5)->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'course' => $session->courseOffering->curriculumUnit->unit_code,
                        'date' => $session->session_date->format('Y-m-d'),
                        'attendance_marked' => $session->attendance_marked,
                        'attendance_percentage' => $session->attendance_percentage,
                    ];
                }),
            ];

            return ApiResponse::success(
                $summary,
                'Attendance summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance summary');
        }
    }
}
