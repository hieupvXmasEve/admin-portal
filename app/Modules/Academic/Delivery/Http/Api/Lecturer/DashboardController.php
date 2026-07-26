<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\DashboardFilterRequest;
use App\Http\Resources\Api\V1\Lecturer\DashboardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Semester;
use App\Modules\Academic\Delivery\Support\LecturerDashboardService;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        protected LecturerDashboardService $dashboardService,
        protected AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * Get lecturer's main dashboard data
     */
    public function index(DashboardFilterRequest $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $filters = $request->validated();

            // Get active semester or use provided semester_id
            $currentPeriod = $this->academicPeriods->current();
            $semesterId = $filters['semester_id'] ?? $currentPeriod?->id;

            Log::info('Dashboard request details', [
                'semester_id' => $semesterId,
                'active_semester_found' => $currentPeriod !== null,
                'lecturer_id' => $lecturer->lecturerId(),
            ]);

            // Handle case when no active semester exists and no semester_id provided
            if (! $semesterId) {
                Log::warning('No active semester found and no semester_id provided for lecturer dashboard', [
                    'lecturer_id' => $lecturer->lecturerId(),
                ]);

                return ApiResponse::success(
                    data: [
                        'semester' => null,
                        'teaching_summary' => $this->getEmptyTeachingSummary(),
                        'attendance_overview' => $this->getEmptyAttendanceOverview(),
                        'student_alerts' => $this->getEmptyStudentAlerts(),
                        'upcoming_sessions' => [],
                        'recent_activities' => [],
                    ],
                    message: 'No active semester found. Dashboard showing empty data.'
                );
            }

            $dashboardData = $this->dashboardService->getDashboardData($lecturer, $semesterId);

            return ApiResponse::success(
                data: new DashboardResource($dashboardData),
                message: 'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Dashboard data retrieval failed', [
                'error' => $e->getMessage(),
                'lecturer_id' => $lecturer->lecturerId(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve dashboard data');
        }
    }

    /**
     * Get teaching summary statistics
     */
    public function teachingSummary(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $semester = $this->semesterFor($semesterId);

            // Handle case when no semester is found
            if (! $semester) {
                Log::info('No semester found for teaching summary', [
                    'requested_semester_id' => $semesterId,
                    'lecturer_id' => $lecturer->lecturerId(),
                ]);

                return ApiResponse::success(
                    data: $this->getEmptyTeachingSummary(),
                    message: 'No active semester found. Showing empty teaching summary.'
                );
            }

            $teachingSummary = $this->dashboardService->getTeachingSummary($lecturer, $semester);

            return ApiResponse::success(
                data: $teachingSummary,
                message: 'Teaching summary retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Teaching summary retrieval failed', [
                'error' => $e->getMessage(),
                'lecturer_id' => $lecturer->lecturerId(),
            ]);

            return ApiResponse::serverError('Failed to retrieve teaching summary');
        }
    }

    /**
     * Get attendance overview with alerts
     */
    public function attendanceOverview(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $semester = $this->semesterFor($semesterId);

            // Handle case when no semester is found
            if (! $semester) {
                Log::info('No semester found for attendance overview', [
                    'requested_semester_id' => $semesterId,
                    'lecturer_id' => $lecturer->lecturerId(),
                ]);

                return ApiResponse::success(
                    data: $this->getEmptyAttendanceOverview(),
                    message: 'No active semester found. Showing empty attendance overview.'
                );
            }

            $attendanceOverview = $this->dashboardService->getAttendanceOverview($lecturer, $semester);

            return ApiResponse::success(
                data: $attendanceOverview,
                message: 'Attendance overview retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Attendance overview retrieval failed', [
                'error' => $e->getMessage(),
                'lecturer_id' => $lecturer->lecturerId(),
            ]);

            return ApiResponse::serverError('Failed to retrieve attendance overview');
        }
    }

    /**
     * Get student alerts for lecturer's courses
     */
    public function studentAlerts(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $semesterId = $request->query('semester_id');
            $semester = $this->semesterFor($semesterId);

            // Handle case when no semester is found
            if (! $semester) {
                Log::info('No semester found for student alerts', [
                    'requested_semester_id' => $semesterId,
                    'lecturer_id' => $lecturer->lecturerId(),
                ]);

                return ApiResponse::success(
                    data: $this->getEmptyStudentAlerts(),
                    message: 'No active semester found. Showing empty student alerts.'
                );
            }

            $studentAlerts = $this->dashboardService->getStudentAlerts($lecturer, $semester);

            return ApiResponse::success(
                data: $studentAlerts,
                message: 'Student alerts retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Student alerts retrieval failed', [
                'error' => $e->getMessage(),
                'lecturer_id' => $lecturer->lecturerId(),
            ]);

            return ApiResponse::serverError('Failed to retrieve student alerts');
        }
    }

    /**
     * Get upcoming sessions for lecturer
     */
    public function upcomingSessions(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $limit = (int) $request->query('limit', 5);
            $limit = min(max($limit, 1), 20); // Ensure limit is between 1 and 20

            $upcomingSessions = $this->dashboardService->getUpcomingSessions($lecturer, $limit);

            return ApiResponse::success(
                data: $upcomingSessions,
                message: 'Upcoming sessions retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve upcoming sessions');
        }
    }

    /**
     * Get recent teaching activities
     */
    public function recentActivities(Request $request): JsonResponse
    {
        /** @var Lecture $lecturer */
        $lecturer = $request->user();

        try {
            $limit = (int) $request->query('limit', 10);
            $limit = min(max($limit, 1), 50); // Ensure limit is between 1 and 50

            $recentActivities = $this->dashboardService->getRecentActivities($lecturer, $limit);

            return ApiResponse::success(
                data: $recentActivities,
                message: 'Recent activities retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve recent activities');
        }
    }

    private function semesterFor(mixed $semesterId): ?Semester
    {
        $resolvedId = is_numeric($semesterId)
            ? (int) $semesterId
            : $this->academicPeriods->current()?->id;

        return $resolvedId === null ? null : Semester::find($resolvedId);
    }

    /**
     * Get empty teaching summary structure
     */
    private function getEmptyTeachingSummary(): array
    {
        return [
            'total_courses' => 0,
            'total_students' => 0,
            'total_sessions' => 0,
            'completed_sessions' => 0,
            'pending_sessions' => 0,
            'average_class_size' => 0,
            'courses' => [],
        ];
    }

    /**
     * Get empty attendance overview structure
     */
    private function getEmptyAttendanceOverview(): array
    {
        return [
            'total_sessions' => 0,
            'sessions_with_attendance' => 0,
            'pending_attendance_marking' => 0,
            'average_attendance_rate' => 0,
            'sessions_requiring_attention' => [],
            'attendance_trends' => [
                'weekly_average' => 0,
                'trend' => 'stable',
                'last_week_change' => 0,
            ],
        ];
    }

    /**
     * Get empty student alerts structure
     */
    private function getEmptyStudentAlerts(): array
    {
        return [
            'total_alerts' => 0,
            'low_attendance_students' => [],
            'recently_absent_students' => [],
            'critical_alerts' => [],
        ];
    }
}
