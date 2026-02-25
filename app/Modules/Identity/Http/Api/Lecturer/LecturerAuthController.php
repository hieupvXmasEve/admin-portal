<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Lecturer\LecturerResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Identity\Actions\CheckScienceStatusAction;
use App\Modules\Identity\Actions\LecturerGoogleLoginAction;
use App\Modules\Identity\Actions\LecturerLoginAction;
use App\Modules\Identity\Actions\LecturerLogoutAction;
use App\Modules\Identity\Actions\LecturerRefreshTokenAction;
use App\Modules\Identity\Http\Requests\Identity\GoogleLoginRequest;
use App\Modules\Identity\Http\Requests\Identity\LecturerLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LecturerAuthController extends Controller
{
    /**
     * Lecturer login with enhanced security
     */
    public function login(LecturerLoginRequest $request): JsonResponse
    {
        try {
            $result = LecturerLoginAction::run($request->validated());

            return ApiResponse::success(
                data: [
                    // 'lecturer' => new LecturerResource($result['lecturer']), // Legacy didn't return lecturer here commented out, but new one returns token
                     // The legacy one returned: token, token_type, expires_in.
                     // The Action returns: token, lecturer.
                     // Let's construct the response to match legacy behavior strictly first, but maybe include lecturer info if useful?
                     // Legacy code:
                     // 'token' => $token,
                     // 'token_type' => 'Bearer',
                     // 'expires_in' => config('sanctum.expiration', 525600),

                     // My Action returns ['token' => ..., 'lecturer' => ...]

                     'token' => $result['token'],
                     'token_type' => 'Bearer',
                     'expires_in' => 8 * 60, // minutes
                ],
                message: 'Login successful'
            );
        } catch (\Exception $e) {
            // Exceptions from Action are usually AuthenticationException or ValidationException
            // If AuthenticationException, ApiResponse can handle or we catch specifically.
            // But standard Exception handling in Laravel should catch AuthException -> 401.
            // Let's rely on global handler or catch to be safe if we want custom format.
            // The Action throws AuthenticationException for credentials.

            // If it's a ValidationException (business logic checks), it renders 422.

            // If general exception (unexpected):
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                 return ApiResponse::authenticationError($e->getMessage());
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Refresh lecturer token
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        if (! $lecturer) {
            return ApiResponse::authenticationError('Invalid token');
        }

        try {
            $currentToken = $request->user()->currentAccessToken();

            $result = LecturerRefreshTokenAction::run($lecturer, $request->device_name);
            $currentToken?->delete();

            return ApiResponse::success(
                data: [
                    'lecturer' => new LecturerResource($lecturer),
                    'token' => $result['token'],
                    'token_type' => $result['token_type'],
                    'expires_in' => $result['expires_in'],
                ],
                message: 'Token refreshed successfully'
            );
        } catch (\Exception $e) {
             return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Logout lecturer
     */
    public function logout(Request $request): JsonResponse
    {
        LecturerLogoutAction::run($request);

        return ApiResponse::success(
            data: null,
            message: 'Logged out successfully'
        );
    }

    /**
     * Get current lecturer profile
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        if (! $lecturer) {
            return ApiResponse::authenticationError('Invalid token');
        }

        // Load relationships for complete profile
        $lecturer->load(['campus', 'courseOfferings.unit', 'courseOfferings.semester']);

        return ApiResponse::success(
            data: new LecturerResource($lecturer),
            message: 'Profile retrieved successfully'
        );
    }

    /**
     * Lecturer Google OAuth Login
     */
    public function loginWithGoogle(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $result = LecturerGoogleLoginAction::run(
                $request->id_token,
                $request->ip(),
                $request->device_name
            );

            return ApiResponse::success(
                data: [
                    'lecturer' => new LecturerResource($result['lecturer']),
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'expires_in' => 8 * 60, // minutes
                ],
                message: 'Google login successful'
            );
        } catch (\Exception $e) {
             if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }
            return ApiResponse::serverError('Failed to authenticate with Google: ' . $e->getMessage());
        }
    }

    /**
     * Check if lecturer is Science
     */
    public function checkScience(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'employee_id' => 'required|string',
        ]);

        try {
            $data = CheckScienceStatusAction::run(
                $request->input('email'),
                $request->input('employee_id')
            );

            return ApiResponse::success(
                data: $data,
                message: 'Science status retrieved successfully'
            );
        } catch (\Exception $e) {
             return ApiResponse::serverError('Failed to check Science status: ' . $e->getMessage());
        }
    }
}
