<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\LoginRequest;
use App\Http\Resources\Api\V1\Lecturer\LecturerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Lecturer login with enhanced security
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting key
        $key = 'lecturer-login:'.$request->ip();

        // Check rate limiting
        // if (RateLimiter::tooManyAttempts($key, 5)) {
        //     $seconds = RateLimiter::availableIn($key);
        //     return ApiResponse::rateLimitError(
        //         "Too many login attempts. Try again in {$seconds} seconds."
        //     );
        // }

        // Find lecturer
        $lecturer = Lecture::where('email', $request->email)->first();

        // Avoid logging sensitive credentials. Only minimal, safe info.
        if ($lecturer) {
            Log::debug('Lecturer found for login attempt', ['lecturer_id' => $lecturer->id]);
        } else {
            Log::debug('No lecturer account found for provided email');
        }

        if (! $lecturer || ! Hash::check($request->password, $lecturer->password ?? '')) {
            // RateLimiter::hit($key, 900); // 15 minutes
            return ApiResponse::authenticationError('Invalid credentials');
        }

        // Check if lecturer account is active
        if (! $lecturer->is_active) {
            return ApiResponse::authorizationError(
                'Account is inactive. Please contact administration.'
            );
        }

        // Check employment status
        if (! in_array($lecturer->employment_status, ['active', 'employed', 'contract_active'])) {
            return ApiResponse::authorizationError(
                'Account access restricted. Please contact HR.'
            );
        }

        // Clear rate limiting on successful login
        RateLimiter::clear($key);

        // Update last login timestamp
        $lecturer->update(['last_login_at' => now()]);

        // Create token
        $token = $lecturer->createToken('lecturer-api', ['lecturer:access'])->plainTextToken;

        return ApiResponse::success(
            data: [
//            'lecturer' => new LecturerResource($lecturer),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 525600), // minutes
            ],
            message: 'Login successful'
        );
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

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $lecturer->createToken('lecturer-api', ['lecturer:access'])->plainTextToken;

        return ApiResponse::success(
            data: [
                'lecturer' => new LecturerResource($lecturer),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration', 525600),
            ],
            message: 'Token refreshed successfully'
        );
    }

    /**
     * Logout lecturer
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Lecture $lecturer */
        $lecturer = $request->user();

        if ($lecturer) {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
        }

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
        $lecturer->load(['campus', 'courseOfferings.curriculumUnit', 'courseOfferings.semester']);

        return ApiResponse::success(
            data: new LecturerResource($lecturer),
            message: 'Profile retrieved successfully'
        );
    }

    /**
     * Lecturer Google OAuth Login
     */
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        try {
            // Validate Google access token
            $response = Http::withToken($request->access_token)
                ->get('https://www.googleapis.com/oauth2/v1/userinfo');

            if ($response->failed()) {
                return ApiResponse::authenticationError('Invalid Google access token');
            }

            $googleUserData = $response->json();

            if (! $googleUserData || ! isset($googleUserData['email'])) {
                return ApiResponse::authenticationError('Unable to retrieve user information from Google');
            }

            // Check for lecturer account
            $lecturer = Lecture::where('email', $googleUserData['email'])->first();

            if (! $lecturer) {
                return ApiResponse::notFound('No lecturer account found with this email. Please contact your administrator.');
            }

            // Update OAuth provider data if not set
            if (! $lecturer->oauth_provider_id) {
                $lecturer->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $googleUserData['id'],
                    'avatar_url' => $lecturer->avatar_url ?: ($googleUserData['picture'] ?? null),
                    'email_verified_at' => $lecturer->email_verified_at ?: now(),
                ]);
            }

            // Check if lecturer account is active
            if (! $lecturer->is_active) {
                return ApiResponse::authorizationError('Account is inactive. Please contact administration.');
            }

            // Check employment status
            if (! in_array($lecturer->employment_status, ['active', 'employed', 'contract_active'])) {
                return ApiResponse::authorizationError('Account access restricted. Please contact HR.');
            }

            // Update last login timestamp
            $lecturer->update(['last_login_at' => now()]);

            // Create token
            $deviceName = $request->device_name ?? 'Lecturer Portal (Google)';
            $token = $lecturer->createToken($deviceName, ['lecturer:access'])->plainTextToken;

            return ApiResponse::success(
                data: [
                    'lecturer' => new LecturerResource($lecturer),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration', 1440), // minutes
                ],
                message: 'Google login successful'
            );

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to authenticate with Google: '.$e->getMessage());
        }
    }
}
