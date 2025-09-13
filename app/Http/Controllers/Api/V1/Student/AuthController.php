<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\LoginRequest;
use App\Http\Requests\Api\V1\Student\RefreshTokenRequest;
use App\Http\Requests\Api\V1\Student\GoogleLoginRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Google\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

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

        if (! $student || ! Hash::check($request->password, $student->password ?? '')) {
            RateLimiter::hit($key, 900); // 15 minutes

            return ApiResponse::authenticationError('Invalid credentials');
        }

        // Student active check
        if (! $student->isActive()) {
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

        return ApiResponse::success(
            data: [
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
            ],
            message: 'Login successful'
        );
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

        return ApiResponse::success(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ],
            message: 'Token refreshed successfully'
        );
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(
            data: null,
            message: 'Logged out successfully'
        );
    }

    /**
     * Get current authenticated student
     */
    public function me(Request $request): JsonResponse
    {
        $student = $request->user();

        return ApiResponse::success(
            data: [
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
            message: 'Student profile retrieved successfully'
        );
    }

    /**
     * Student Google OAuth Login with ID Token validation
     */
    public function loginWithGoogle(GoogleLoginRequest $request): JsonResponse
    {
        // Rate limiting for Google OAuth attempts
        $key = 'google-login:' . $request->ip();

//        if (RateLimiter::tooManyAttempts($key, 10)) {
//            $seconds = RateLimiter::availableIn($key);
//
//            return ApiResponse::rateLimitError(
//                "Too many Google login attempts. Try again in {$seconds} seconds."
//            );
//        }

        try {
            Log::debug('[StudentAuth] Google login attempt started', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
            ]);

            // Initialize Google Client and verify ID token
            $client = new Client([
                'client_id' => config('services.google.client_id'),
            ]);
            Log::debug('[StudentAuth] Google login request started', [
                'ip' => $request->ip(),
                'id_token' => $request->id_token,
            ]);

            // Verify the ID token
            $payload = $client->verifyIdToken($request->id_token);

            if (!$payload) {
//                RateLimiter::hit($key, 300); // 5 minutes
                Log::warning('[StudentAuth] Google ID token verification failed', [
                    'ip' => $request->ip(),
                ]);
                return ApiResponse::authenticationError('Invalid Google ID token');
            }

            // Extract user data from the verified payload
            $googleUserData = [
                'id' => $payload['sub'],
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? null,
                'picture' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];

            Log::debug('[StudentAuth] Google ID token verified successfully', [
                'ip' => $request->ip(),
                'email' => $googleUserData['email'],
                'email_verified' => $googleUserData['email_verified'],
            ]);

            // Validate required user data
            if (!$googleUserData['email']) {
                RateLimiter::hit($key, 300);
                Log::warning('[StudentAuth] Google user data missing email', [
                    'ip' => $request->ip(),
                ]);
                return ApiResponse::authenticationError('Unable to retrieve email from Google account');
            }

            if (!$googleUserData['email_verified']) {
                RateLimiter::hit($key, 300);
                Log::warning('[StudentAuth] Google account email not verified', [
                    'ip' => $request->ip(),
                    'email' => $googleUserData['email'],
                ]);
                return ApiResponse::authenticationError('Google account email is not verified');
            }

            // Find student by email
            $student = Student::where('email', $googleUserData['email'])->first();
            Log::debug('[StudentAuth] Student lookup by email', [
                'ip' => $request->ip(),
                'email' => $googleUserData['email'],
                'found' => (bool) $student,
                'student_id' => $student?->id,
            ]);

            if (!$student) {
                RateLimiter::hit($key, 300);
                Log::warning('[StudentAuth] No student account found for Google email', [
                    'ip' => $request->ip(),
                    'email' => $googleUserData['email'],
                ]);
                return ApiResponse::authenticationError('No student account found with this email address');
            }

            // Update OAuth provider data if not already set
            if (!$student->oauth_provider_id) {
                Log::debug('[StudentAuth] Updating OAuth provider fields for student', [
                    'ip' => $request->ip(),
                    'student_id' => $student->id,
                ]);
                $student->update([
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $googleUserData['id'],
                    'avatar_url' => $student->avatar_url ?: $googleUserData['picture'],
                    'email_verified_at' => $student->email_verified_at ?: now(),
                ]);
            }

            // Student active check
            if (!$student->isActive()) {
                RateLimiter::hit($key, 900); // 15 minutes
                Log::warning('[StudentAuth] Student account blocked by status', [
                    'ip' => $request->ip(),
                    'student_id' => $student->id,
                    'status' => $student->status,
                ]);
                return ApiResponse::authorizationError('Student account is not active. Please contact administration.');
            }

            // Check for blocking academic holds
            $blockingHolds = $student->academicHolds()
                ->where('status', 'active')
                ->where('hold_category', 'all')
                ->exists();

            if ($blockingHolds) {
                RateLimiter::hit($key, 900);
                Log::warning('[StudentAuth] Student has blocking academic holds', [
                    'ip' => $request->ip(),
                    'student_id' => $student->id,
                ]);
                return ApiResponse::authorizationError('Account access is restricted due to academic holds.');
            }

            // Clear rate limiting on successful authentication
            RateLimiter::clear($key);

            // Update last login timestamp
            $student->update(['last_login_at' => now()]);
            Log::debug('[StudentAuth] Student last_login_at updated', [
                'ip' => $request->ip(),
                'student_id' => $student->id,
            ]);

            // Create authentication token
            $deviceName = $request->device_name ?? 'Student Portal (Google)';
            $expiresAt = $request->remember_me ? now()->addDays(30) : now()->addHours(8);
            $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

            Log::debug('[StudentAuth] Student token created via Google', [
                'ip' => $request->ip(),
                'student_id' => $student->id,
                'email' => $student->email,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->toISOString(),
                'remember_me' => $request->remember_me,
            ]);

            return ApiResponse::success(
                data: [
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
                ],
                message: 'Google login successful'
            );
        } catch (\Google\Exception $e) {
            RateLimiter::hit($key, 300);
            Log::error('[StudentAuth] Google library error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'id_token_preview' => substr($request->id_token, 0, 50) . '...',
            ]);

            // Provide more specific error message based on the error
            if (str_contains($e->getMessage(), 'Wrong number of segments')) {
                return ApiResponse::authenticationError(
                    'Invalid Google ID token format. Please ensure you are sending a valid JWT token from Google OAuth, not an email address or other value.'
                );
            }

            return ApiResponse::authenticationError('Google authentication failed: Invalid ID token');
        } catch (\Exception $e) {
            RateLimiter::hit($key, 60);
            Log::error('[StudentAuth] Google login unexpected error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::serverError('An unexpected error occurred during Google authentication');
        }
    }

    /**
     * Validate Google ID Token format (for debugging)
     */
    public function validateGoogleToken(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $idToken = $request->id_token;

        // Basic JWT format validation
        $segments = explode('.', $idToken);

        $response = [
            'is_valid_jwt_format' => count($segments) === 3,
            'segments_count' => count($segments),
            'token_length' => strlen($idToken),
            'starts_with_ey' => str_starts_with($idToken, 'ey'),
            'contains_email_pattern' => str_contains($idToken, '@'),
            'preview' => substr($idToken, 0, 50) . '...',
        ];

        if (count($segments) === 3) {
            try {
                // Try to decode the header and payload (without verification)
                $header = json_decode(base64_decode($segments[0]), true);
                $payload = json_decode(base64_decode($segments[1]), true);

                $response['header'] = $header;
                $response['payload_preview'] = [
                    'iss' => $payload['iss'] ?? null,
                    'aud' => $payload['aud'] ?? null,
                    'exp' => $payload['exp'] ?? null,
                    'email' => $payload['email'] ?? null,
                ];
            } catch (\Exception $e) {
                $response['decode_error'] = $e->getMessage();
            }
        }

        $message = count($segments) === 3
            ? 'Token format appears valid'
            : 'Invalid JWT format. Expected 3 segments separated by dots, got ' . count($segments);

        return ApiResponse::success($response, [], $message);
    }
}
