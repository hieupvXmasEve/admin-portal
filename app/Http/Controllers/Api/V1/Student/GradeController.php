<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\GradeFilterRequest;
use App\Http\Resources\Api\V1\Student\AssessmentDetailResource;
use App\Http\Resources\Api\V1\Student\AssessmentResource;
use App\Http\Resources\Api\V1\Student\CourseGradeResource;
use App\Http\Resources\Api\V1\Student\GradeResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function __construct(
        protected GradeService $gradeService
    ) {}

    /**
     * Get student's grades
     */
    public function index(GradeFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $filters = $request->validated();
            $semesterId = $filters['semester_id'] ?? null;
            unset($filters['semester_id']);

            $grades = $this->gradeService->getStudentGrades($student, $semesterId, $filters);

            return ApiResponse::success(
                new GradeResource($grades),
                'Grades retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve grades');
        }
    }

    /**
     * Get GPA trend analysis
     */
    public function gpaTrend(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $semesterCount = $request->query('semester_count', 8);
            $gpaTrend = $this->gradeService->getGPATrend($student, (int) $semesterCount);

            return ApiResponse::success(
                $gpaTrend,
                'GPA trend retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve GPA trend');
        }
    }

    /**
     * Get grades for a specific course
     */
    public function courseGrades(Request $request, int $courseOfferingId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $courseGrades = $this->gradeService->getCourseGrades($student, $courseOfferingId);

            return ApiResponse::success(
                new CourseGradeResource($courseGrades),
                'Course grades retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Get assessments
     */
    public function assessments(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $filters = $request->only([
                'semester_id',
                'assessment_type',
                'course_code',
                'status',
            ]);

            $assessments = $this->gradeService->getAssessments($student, $filters);

            return ApiResponse::success(
                AssessmentResource::collection($assessments['assessments'])->additional([
                    'summary' => $assessments['summary'],
                    'upcoming' => $assessments['upcoming'],
                    'performance_by_type' => $assessments['performance_by_type'],
                ]),
                'Assessments retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve assessments');
        }
    }

    /**
     * Get assessment detail
     */
    public function assessmentDetail(Request $request, int $assessmentId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $assessmentDetail = $this->gradeService->getAssessmentDetail($student, $assessmentId);

            return ApiResponse::success(
                new AssessmentDetailResource($assessmentDetail),
                'Assessment detail retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }
}
