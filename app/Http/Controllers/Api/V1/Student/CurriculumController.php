<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\CurriculumBySemesterResource;
use App\Http\Resources\Api\V1\Student\CurriculumOverviewResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\CurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CurriculumController extends Controller
{
    public function __construct(
        protected CurriculumService $curriculumService
    ) {}

    /**
     * Get curriculum overview
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $curriculumOverview = $this->curriculumService->getCurriculumOverview($student);
            Log::info('$curriculumOverview', [
                'student' => $student,
                '$curriculumOverview' => $curriculumOverview,
            ]);
            return ApiResponse::success(
                new CurriculumOverviewResource($curriculumOverview),
                [],
                'Curriculum overview retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve curriculum overview');
        }
    }

    /**
     * Get prerequisite tree
     */
//    public function prerequisiteTree(Request $request): JsonResponse
//    {
//        /** @var \App\Models\Student $student */
//        $student = $request->user();
//
//        try {
//            $prerequisiteTree = $this->curriculumService->getPrerequisiteTree($student);
//
//            return ApiResponse::success(
//                new PrerequisiteTreeResource($prerequisiteTree),
//                [],
//                'Prerequisite tree retrieved successfully'
//            );
//        } catch (\Exception $e) {
//            return ApiResponse::serverError('Failed to retrieve prerequisite tree');
//        }
//    }

    /**
     * Get program requirements
     */
    public function programRequirements(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $programRequirements = $this->curriculumService->getProgramRequirements($student);

            return ApiResponse::success(
                $programRequirements,
                [],
                'Program requirements retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve program requirements');
        }
    }

    /**
     * Get academic roadmap
     */
    public function roadmap(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $roadmap = $this->curriculumService->getAcademicRoadmap($student);

            return ApiResponse::success(
                $roadmap,
                [],
                'Academic roadmap retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve academic roadmap');
        }
    }

    /**
     * Get curriculum organized by semester with grades and study status
     */
    public function bySemester(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $curriculumBySemester = $this->curriculumService->getCurriculumBySemester($student);

            return ApiResponse::success(
                new CurriculumBySemesterResource($curriculumBySemester),
                [],
                'Curriculum by semester retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve curriculum by semester', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to retrieve curriculum by semester');
        }
    }
}
