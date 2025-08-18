<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\AvatarUploadRequest;
use App\Http\Requests\Api\V1\Student\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\Student\AcademicHistoryResource;
use App\Http\Resources\Api\V1\Student\ProfileResource;
use App\Http\Resources\Api\V1\Student\StudyPlanResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    /**
     * Get student profile information
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $profile = $this->profileService->getProfile($student);

            return ApiResponse::success(
                data: new ProfileResource($profile),
                message: 'Profile retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Profile show failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::serverError('Failed to retrieve profile');
        }
    }

    /**
     * Update student profile
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $data = $request->validated();
            $success = $this->profileService->updateProfile($student, $data);

            if ($success) {
                return ApiResponse::success(
                    data: null,
                    message: 'Profile updated successfully'
                );
            } else {
                return ApiResponse::businessLogicError('Failed to update profile');
            }
        } catch (\Throwable $e) {
            Log::channel('api')->error('Profile update failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::serverError('Failed to update profile');
        }
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(AvatarUploadRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $file = $request->file('avatar');
            $result = $this->profileService->uploadAvatar($student, $file);

            return ApiResponse::success(
                data: $result,
                message: 'Avatar uploaded successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Avatar upload failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::serverError('Failed to upload avatar');
        }
    }

    /**
     * Get study plan
     */
    public function studyPlan(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $studyPlan = $this->profileService->getStudyPlan($student);

            return ApiResponse::success(
                data: new StudyPlanResource($studyPlan),
                message: 'Study plan retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::info('error' , [
                'message' => $e->getMessage(),
            ]);
            Log::channel('api')->error('Study plan retrieval failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::serverError('Failed to retrieve study plan');
        }
    }

    /**
     * Get academic history
     */
    public function academicHistory(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $academicHistory = $this->profileService->getAcademicHistory($student);

            return ApiResponse::success(
                data: new AcademicHistoryResource($academicHistory),
                message: 'Academic history retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Academic history retrieval failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiResponse::serverError('Failed to retrieve academic history');
        }
    }
}
