<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\DashboardFilterRequest;
use App\Http\Resources\Api\V1\Lecturer\DashboardResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Lecturer\LecturerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected LecturerDashboardService $dashboardService
    ) {}

    /**
     * Get lecturer's main dashboard data
     */
    public function index(DashboardFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $filters = $request->validated();
            $semesterId = $filters['semester_id'] ?? null;
            
            $dashboardData = $this->dashboardService->getDashboardData($lecturer, $semesterId);
            
            return ApiResponse::success(
                new DashboardResource($dashboardData),
                'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve dashboard data');
        }
    }

    /**
     * Get teaching summary statistics
     */
    public function teachingSummary(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $semesterId = $request->query('semester_id');
            $semester = $semesterId ? \App\Models\Semester::find($semesterId) : \App\Models\Semester::getActiveSemester();

            $teachingSummary = $this->dashboardService->getTeachingSummary($lecturer, $semester);
            
            return ApiResponse::success(
                $teachingSummary,
                'Teaching summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve teaching summary');
        }
    }

    /**
     * Get attendance overview with alerts
     */
    public function attendanceOverview(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $semesterId = $request->query('semester_id');
            $semester = $semesterId ? \App\Models\Semester::find($semesterId) : \App\Models\Semester::getActiveSemester();

            $attendanceOverview = $this->dashboardService->getAttendanceOverview($lecturer, $semester);
            
            return ApiResponse::success(
                $attendanceOverview,
                'Attendance overview retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve attendance overview');
        }
    }

    /**
     * Get student alerts for lecturer's courses
     */
    public function studentAlerts(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $semesterId = $request->query('semester_id');
            $semester = $semesterId ? \App\Models\Semester::find($semesterId) : \App\Models\Semester::getActiveSemester();

            $studentAlerts = $this->dashboardService->getStudentAlerts($lecturer, $semester);
            
            return ApiResponse::success(
                $studentAlerts,
                'Student alerts retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve student alerts');
        }
    }

    /**
     * Get upcoming sessions for lecturer
     */
    public function upcomingSessions(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $limit = (int) $request->query('limit', 5);
            $limit = min(max($limit, 1), 20); // Ensure limit is between 1 and 20
            
            $upcomingSessions = $this->dashboardService->getUpcomingSessions($lecturer, $limit);
            
            return ApiResponse::success(
                $upcomingSessions,
                'Upcoming sessions retrieved successfully'
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
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();
        
        try {
            $limit = (int) $request->query('limit', 10);
            $limit = min(max($limit, 1), 50); // Ensure limit is between 1 and 50
            
            $recentActivities = $this->dashboardService->getRecentActivities($lecturer, $limit);
            
            return ApiResponse::success(
                $recentActivities,
                'Recent activities retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve recent activities');
        }
    }
}
