<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\StudentRegistry\Actions\UpdateStudentProfileAction;
use App\Modules\StudentRegistry\Actions\UploadStudentPortalAvatarAction;
use App\Modules\StudentRegistry\Http\Requests\Student\AvatarUploadRequest;
use App\Modules\StudentRegistry\Http\Requests\Student\ProfileUpdateRequest;
use App\Modules\StudentRegistry\Http\Resources\StudentProfileResource;
use App\Shared\Contracts\StudentRegistry\StudentPortalProfileReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final class ProfileController extends Controller
{
    public function show(Request $request, StudentPortalProfileReader $profiles): JsonResponse
    {
        try {
            return ApiResponse::success(
                data: new StudentProfileResource($profiles->forStudent((int) $request->user()->getKey())->toArray()),
                message: 'Profile retrieved successfully',
            );
        } catch (\Throwable $exception) {
            Log::channel('api')->error('Profile show failed', ['exception' => $exception::class]);

            return ApiResponse::serverError('Failed to retrieve profile');
        }
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        try {
            $studentId = (int) $request->user()->getKey();
            $updated = UpdateStudentProfileAction::run([
                'student_id' => $studentId,
                'attributes' => $request->validated(),
            ]);
            UploadStudentPortalAvatarAction::clearCache($studentId);

            return $updated
                ? ApiResponse::success(message: 'Profile updated successfully')
                : ApiResponse::businessLogicError('Failed to update profile');
        } catch (\Throwable $exception) {
            Log::channel('api')->error('Profile update failed', ['exception' => $exception::class]);

            return ApiResponse::serverError('Failed to update profile');
        }
    }

    public function uploadAvatar(AvatarUploadRequest $request): JsonResponse
    {
        try {
            $avatar = $request->file('avatar');
            if (! $avatar instanceof UploadedFile) {
                return ApiResponse::validationError(['avatar' => ['Avatar image is required']], 'Invalid avatar file');
            }

            return ApiResponse::success(
                data: UploadStudentPortalAvatarAction::run([
                    'student_id' => (int) $request->user()->getKey(),
                    'file' => $avatar,
                ]),
                message: 'Avatar uploaded successfully',
            );
        } catch (\Throwable $exception) {
            Log::channel('api')->error('Avatar upload failed', ['exception' => $exception::class]);

            return ApiResponse::serverError('Failed to upload avatar');
        }
    }
}
