<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\DashboardResource;
use App\Http\Resources\Api\V1\Student\GPAResource;
use App\Http\Responses\ApiResponse;
use App\Shared\Contracts\Academic\StudentDashboardReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(
        protected StudentDashboardReader $dashboardReader
    ) {}

    /**
     * Get complete dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        try {
            $dashboardData = $this->dashboardReader->dashboardForStudent((int) $student->id);

            return ApiResponse::success(
                new DashboardResource($dashboardData),
                [],
                'Dashboard data retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());

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
            $gpaData = $this->dashboardReader->gpaForStudent((int) $student->id);

            return ApiResponse::success(
                new GPAResource($gpaData),
                [],
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
            $creditProgress = $this->dashboardReader->creditProgressForStudent((int) $student->id);

            return ApiResponse::success(
                $creditProgress,
                [],
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
            $academicHolds = $this->dashboardReader->academicHoldsForStudent((int) $student->id);

            return ApiResponse::success(
                $academicHolds,
                [],
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
            $upcomingAssessments = $this->dashboardReader->upcomingAssessmentsForStudent((int) $student->id);

            return ApiResponse::success(
                $upcomingAssessments,
                [],
                'Upcoming assessments retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve upcoming assessments');
        }
    }
}
