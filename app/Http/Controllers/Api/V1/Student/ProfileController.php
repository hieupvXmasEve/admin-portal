<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\AcademicHistoryResource;
use App\Http\Resources\Api\V1\Student\StudyPlanResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Temporary compatibility adapter for student academic history endpoints.
 *
 * Profile identity, contact, and avatar endpoints are owned by StudentRegistry.
 * This adapter remains until the Progression-owned study-plan and history reads
 * are available under issue 12.
 */
final class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    public function studyPlan(Request $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                data: new StudyPlanResource($this->profileService->getStudyPlan($request->user())),
                message: 'Study plan retrieved successfully',
            );
        } catch (\Throwable $exception) {
            Log::channel('api')->error('Study plan retrieval failed', ['exception' => $exception::class]);

            return ApiResponse::serverError('Failed to retrieve study plan');
        }
    }

    public function academicHistory(Request $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                data: new AcademicHistoryResource($this->profileService->getAcademicHistory($request->user())),
                message: 'Academic history retrieved successfully',
            );
        } catch (\Throwable $exception) {
            Log::channel('api')->error('Academic history retrieval failed', ['exception' => $exception::class]);

            return ApiResponse::serverError('Failed to retrieve academic history');
        }
    }
}
