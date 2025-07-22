<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\DashboardResource;
use App\Http\Resources\Api\V1\Student\GPAResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get complete dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        try {
            $dashboardData = $this->dashboardService->getDashboardData($student);

            return ApiResponse::success(
                new DashboardResource($dashboardData),
                'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve dashboard data');
        }
    }

    /**
     * Get GPA information
     */
    public function gpa(Request $request): JsonResponse
    {
        $student = $request->user();

        try {
            $gpaData = $this->dashboardService->getGPAData($student);

            return ApiResponse::success(
                new GPAResource($gpaData),
                'GPA data retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve GPA data');
        }
    }

    /**
     * Get credit progress information
     */
    public function creditProgress(Request $request): JsonResponse
    {
        $student = $request->user();
        
        try {
            $creditProgress = $this->dashboardService->getCreditProgress($student);
            
            return ApiResponse::success(
                $creditProgress,
                'Credit progress retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve credit progress');
        }
    }

    /**
     * Get academic holds
     */
    public function academicHolds(Request $request): JsonResponse
    {
        $student = $request->user();
        
        try {
            $academicHolds = $this->dashboardService->getAcademicHolds($student);
            
            return ApiResponse::success(
                $academicHolds,
                'Academic holds retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve academic holds');
        }
    }

    /**
     * Get upcoming assessments
     */
    public function upcomingAssessments(Request $request): JsonResponse
    {
        $student = $request->user();
        
        try {
            $upcomingAssessments = $this->dashboardService->getUpcomingAssessments($student);
            
            return ApiResponse::success(
                $upcomingAssessments,
                'Upcoming assessments retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve upcoming assessments');
        }
    }
}
