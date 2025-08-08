<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\LoginRequest;
use App\Http\Requests\Api\V1\Student\RefreshTokenRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Student login with enhanced security
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting key
        $key = 'login:'.$request->ip();

        // Check rate limiting
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return ApiResponse::rateLimitError(
                "Too many login attempts. Try again in {$seconds} seconds."
            );
        }

        // Request is already validated by LoginRequest

        // Find student
        $student = Student::where('email', $request->email)->first();

        if (! $student || ! Hash::check($request->password, $student->password ?? '')) {
            RateLimiter::hit($key, 900); // 15 minutes

            return ApiResponse::authenticationError('Invalid credentials');
        }

        // Check if student account is active
        if (! in_array($student->status, ['active', 'enrolled'])) {
            return ApiResponse::authorizationError(
                'Account is not active. Please contact administration.'
            );
        }

        // Check for blocking academic holds
        $blockingHolds = $student->academicHolds()
            ->where('status', 'active')
            ->where('hold_category', 'all')
            ->exists();

        if ($blockingHolds) {
            return ApiResponse::authorizationError(
                'Account access is restricted due to academic holds.'
            );
        }

        // Clear rate limiting on successful login
        RateLimiter::clear($key);

        // Update last login
        $student->update(['last_login_at' => now()]);

        // Create token with appropriate expiration
        $deviceName = $request->device_name ?? 'Student Portal';
        $expiresAt = $request->remember_me ? now()->addDays(30) : now()->addHours(8);

        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'campus' => $student->campus?->name,
                'program' => $student->program?->name,
                'specialization' => $student->specialization?->name,
                'avatar_url' => $student->avatar_url,
            ],
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ], 'Login successful');
    }

    /**
     * Refresh authentication token
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        // Request is already validated by RefreshTokenRequest

        $student = $request->user();

        if (! $student instanceof Student) {
            return ApiResponse::authenticationError('Invalid token');
        }

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $deviceName = $request->device_name ?? 'Student Portal';
        $expiresAt = now()->addHours(8);

        $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toISOString(),
        ], 'Token refreshed successfully');
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    /**
     * Get current authenticated student
     */
    public function me(Request $request): JsonResponse
    {
        $student = $request->user();

        return ApiResponse::success([
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'status' => $student->status,
                'academic_status' => $student->academic_status,
                'campus' => [
                    'id' => $student->campus?->id,
                    'name' => $student->campus?->name,
                    'code' => $student->campus?->code,
                ],
                'program' => [
                    'id' => $student->program?->id,
                    'name' => $student->program?->name,
                    'code' => $student->program?->code,
                ],
                'specialization' => $student->specialization ? [
                    'id' => $student->specialization->id,
                    'name' => $student->specialization->name,
                ] : null,
                'curriculum_version' => [
                    'id' => $student->curriculumVersion?->id,
                    'name' => $student->curriculumVersion?->name,
                    'version' => $student->curriculumVersion?->version,
                ],
                'avatar_url' => $student->avatar_url,
                'admission_date' => $student->admission_date?->toDateString(),
                'expected_graduation_date' => $student->expected_graduation_date?->toDateString(),
                'last_login_at' => $student->last_login_at?->toISOString(),
            ],
        ], 'Student profile retrieved successfully');
    }

    /**
     * Student Google OAuth Login
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

            // Check for student account
            $student = Student::where('email', $googleUserData['email'])->first();

            if (! $student) {
                // Handle student registration if allowed
                if (config('app.allow_student_registration', false)) {
                    $student = Student::create([
                        'full_name' => $googleUserData['name'] ?? $googleUserData['email'],
                        'email' => $googleUserData['email'],
                        'oauth_provider' => 'google',
                        'oauth_provider_id' => $googleUserData['id'],
                        'avatar_url' => $googleUserData['picture'] ?? null,
                        'status' => 'inactive', // Requires admin activation
                        'email_verified_at' => now(),
                    ]);

                    return ApiResponse::success([
                        'student' => [
                            'id' => $student->id,
                            'full_name' => $student->full_name,
                            'email' => $student->email,
                            'status' => $student->status,
                        ],
                    ], 'Student account created successfully. Please wait for admin approval.', 201);
                }

                return ApiResponse::notFoundError('No student account found with this email. Please contact your administrator.');
            }

            // Update OAuth provider data if not set
            if (! $student->oauth_provider_id) {
                $student->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $googleUserData['id'],
                    'avatar_url' => $student->avatar_url ?: ($googleUserData['picture'] ?? null),
                    'email_verified_at' => $student->email_verified_at ?: now(),
                ]);
            }

            // Check if student account is active
            if (! in_array($student->status, ['active', 'enrolled'])) {
                return ApiResponse::authorizationError('Student account is not active');
            }

            // Check for blocking academic holds
            $blockingHolds = $student->academicHolds()
                ->where('status', 'active')
                ->where('hold_category', 'all')
                ->exists();

            if ($blockingHolds) {
                return ApiResponse::authorizationError('Account access is restricted due to academic holds.');
            }

            // Update last login
            $student->update(['last_login_at' => now()]);

            // Create token
            $deviceName = $request->device_name ?? 'Student Portal (Google)';
            $expiresAt = now()->addHours(8);
            $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

            return ApiResponse::success([
                'student' => [
                    'id' => $student->id,
                    'student_code' => $student->student_code,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                    'campus' => $student->campus?->name,
                    'program' => $student->program?->name,
                    'specialization' => $student->specialization?->name,
                    'avatar_url' => $student->avatar_url,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ], 'Google login successful');

        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to authenticate with Google: '.$e->getMessage());
        }
    }
}
