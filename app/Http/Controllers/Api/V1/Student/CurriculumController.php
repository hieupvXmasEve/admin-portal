<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\AcademicRoadmapResource;
use App\Http\Resources\Api\V1\Student\CurriculumOverviewResource;
use App\Http\Resources\Api\V1\Student\PrerequisiteTreeResource;
use App\Http\Resources\Api\V1\Student\ProgramRequirementsResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\CurriculumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

            return ApiResponse::success(
                new CurriculumOverviewResource($curriculumOverview),
                'Curriculum overview retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve curriculum overview');
        }
    }

    /**
     * Get prerequisite tree
     */
    public function prerequisiteTree(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $prerequisiteTree = $this->curriculumService->getPrerequisiteTree($student);

            return ApiResponse::success(
                new PrerequisiteTreeResource($prerequisiteTree),
                'Prerequisite tree retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve prerequisite tree');
        }
    }

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
                new ProgramRequirementsResource($programRequirements),
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
                new AcademicRoadmapResource($roadmap),
                'Academic roadmap retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve academic roadmap');
        }
    }
}
