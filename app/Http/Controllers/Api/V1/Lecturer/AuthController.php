<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Lecturer\LoginRequest;
use App\Http\Requests\Api\V1\Student\GoogleLoginRequest;
use App\Http\Resources\Api\V1\Lecturer\LecturerResource;
use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use Google\Client;
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
        $key = 'lecturer-login:' . $request->ip();

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
        // Rate limiting for Google OAuth attempts
        //        $key = 'google-login:' . $request->ip();

        try {

            $client = new Client([
                'client_id' => config('services.google.client_id'),
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
            $expiresAt = $request->remember_me ? now()->addDays(30) : now()->addHours(8);
            $token = $lecturer->createToken($deviceName, ['lecturer'], $expiresAt)->plainTextToken;

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

        $email = $request->input('email');
        $employeeId = $request->input('employee_id');

        try {
            // Generate checksum URL
            $url = $this->generateScienceCheckUrl($email, $employeeId);

            // Make HTTP request
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return ApiResponse::serverError('Failed to check Science status');
            }

            $data = $response->json();

            return ApiResponse::success(
                data: $data,
                message: 'Science status retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to check Science status', [
                'email' => $email,
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to check Science status: ' . $e->getMessage());
        }
    }

    /**
     * Generate Science check URL with checksum
     */
    private function generateScienceCheckUrl(string $email, string $employeeId): string
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $scienceHashCode = 'aba51784ad6cf5a6ec1b69e02c3b00f79f5d32d87ea20a558be7eec1fb6bd624';

        // tương đương: email + "FAP" + dd/MM/yyyy HH:00
        $value = $email . 'FAP' . date('d/m/Y H:00');
        $checksum = $this->getCheckSum($value, $scienceHashCode);

        return "https://hr.fpt.edu.vn/Science/CheckIsScience?email={$email}&employeeId={$employeeId}&checksum={$checksum}";
    }

    /**
     * Generate checksum using HMAC-SHA1 and Base64 encoding
     */
    private function getCheckSum(string $value, string $hashKey): string
    {
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $value, $hashKey, true);

        // Base64 encode
        $checksum = base64_encode($hash);

        // Replace
        $checksum = str_replace('=', '%3d', $checksum);
        $checksum = str_replace(' ', '+', $checksum);

        return $checksum;
    }
}
