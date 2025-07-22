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
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /**
     * Student login with enhanced security
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting key
        $key = 'login:' . $request->ip();
        
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

        if (!$student || !Hash::check($request->password, $student->password ?? '')) {
            RateLimiter::hit($key, 900); // 15 minutes
            return ApiResponse::authenticationError('Invalid credentials');
        }

        // Check if student account is active
        if (!in_array($student->status, ['active', 'enrolled'])) {
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
                'student_id' => $student->student_id,
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
        
        if (!$student instanceof Student) {
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
                'student_id' => $student->student_id,
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
}
