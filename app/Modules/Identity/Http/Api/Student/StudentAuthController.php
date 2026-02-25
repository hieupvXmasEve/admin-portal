<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\StudentLoginAction;
use App\Modules\Identity\Actions\StudentGoogleLoginAction;
use App\Modules\Identity\Actions\RefreshTokenAction;
use App\Modules\Identity\Actions\StudentLogoutAction;
use App\Modules\Identity\Http\Requests\Identity\StudentLoginRequest;
use App\Modules\Identity\Http\Requests\Identity\RefreshTokenRequest;
use App\Modules\Identity\Http\Requests\Identity\GoogleLoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentAuthController extends Controller
{
    public function login(StudentLoginRequest $request): JsonResponse
    {
        try {
            $result = StudentLoginAction::run($request->validated(), $request->ip());
            return ApiResponse::success($result, [], 'Login successful');
        } catch (\Exception $e) {
            return ApiResponse::authenticationError($e->getMessage());
        }
    }

    public function loginWithGoogle(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $result = StudentGoogleLoginAction::run(
                $request->id_token,
                $request->ip(),
                $request->device_name
            );
            return ApiResponse::success($result, [], 'Google login successful');
        } catch (\Exception $e) {
            return ApiResponse::authenticationError($e->getMessage());
        }
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $student = $request->user();
            $currentToken = $student->currentAccessToken();

            $result = RefreshTokenAction::run($student, $request->device_name);
            $currentToken?->delete();
            return ApiResponse::success($result, [], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return ApiResponse::authenticationError($e->getMessage());
        }
    }

    public function logout(Request $request): JsonResponse
    {
        StudentLogoutAction::run($request);
        return ApiResponse::success(null, [], 'Logged out successfully');
    }
}
