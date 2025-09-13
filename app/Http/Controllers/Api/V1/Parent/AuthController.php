<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Parent\LoginRequest;
use App\Http\Requests\Api\V1\Parent\RegisterRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\Parent\ParentAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(private readonly ParentAuthService $service) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $student] = $this->service->register($request->validated());

        $deviceName = $request->device_name ?? 'Parent Portal';
        $expiresAt = now()->addHours(720);
        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                ],
                'linked_student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'campus_id' => $student->campus_id,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ],
            message: 'Parent registered successfully'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'parent_login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return ApiResponse::rateLimitError("Too many login attempts. Try again in {$seconds} seconds.");
        }

        $user = $this->service->login($request->identifier, $request->password);
        if (! $user instanceof User) {
            RateLimiter::hit($key, 900);
            return ApiResponse::authenticationError('Invalid credentials');
        }

        RateLimiter::clear($key);

        $deviceName = $request->device_name ?? 'Parent Portal';
        $expiresAt = $request->boolean('remember_me') ? now()->addDays(30) : now()->addHours(720);
        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ],
            message: 'Login successful'
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::authenticationError('Invalid token');
        }

        $request->user()->currentAccessToken()->delete();

        $deviceName = $request->device_name ?? 'Parent Portal';
        $expiresAt = now()->addHours(8);
        $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;

        return ApiResponse::success(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toISOString(),
            ],
            message: 'Token refreshed successfully'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return ApiResponse::success(data: null, message: 'Logged out successfully');
    }

    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::debug('[ParentAuth] Google login validation failed', [
                'ip' => $request->ip(),
                'errors' => $validator->errors()->toArray(),
            ]);
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        try {
            Log::debug('[ParentAuth] Google login attempt started', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
            ]);
            // Validate Google access token
            $response = Http::withToken($request->access_token)
                ->get('https://www.googleapis.com/oauth2/v1/userinfo');

            if ($response->failed()) {
                Log::warning('[ParentAuth] Google userinfo request failed', [
                    'ip' => $request->ip(),
                    'status' => $response->status(),
                    'reason' => $response->reason(),
                ]);
                return ApiResponse::authenticationError('Invalid Google access token');
            }

            $googleUserData = $response->json();
            Log::debug('[ParentAuth] Google userinfo response received', [
                'ip' => $request->ip(),
                'status' => $response->status(),
                'has_email' => isset($googleUserData['email']),
            ]);

            if (! $googleUserData || ! isset($googleUserData['email'])) {
                Log::warning('[ParentAuth] Google userinfo missing email', [
                    'ip' => $request->ip(),
                ]);
                return ApiResponse::authenticationError('Unable to retrieve user information from Google');
            }

            // Find user by email
            $user = User::where('email', $googleUserData['email'])->first();
            Log::debug('[ParentAuth] Parent lookup by email', [
                'ip' => $request->ip(),
                'email' => $googleUserData['email'],
                'found' => (bool) $user,
                'user_id' => $user?->id,
            ]);
            if (! $user) {
                Log::warning('[ParentAuth] Parent account not found for Google email', [
                    'ip' => $request->ip(),
                    'email' => $googleUserData['email'],
                ]);
                return ApiResponse::authenticationError('Parent account not found with this email');
            }

            // Check if user is active
            if (! $user->isActive()) {
                Log::warning('[ParentAuth] Parent account inactive', [
                    'ip' => $request->ip(),
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
                return ApiResponse::authorizationError('Parent account is not active');
            }

            // Create token
            $deviceName = $request->device_name ?? 'Parent Portal (Google)';
            $expiresAt = now()->addHours(8);
            $token = $user->createToken($deviceName, ['parent'], $expiresAt)->plainTextToken;
            Log::debug('[ParentAuth] Parent token created via Google', [
                'ip' => $request->ip(),
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->toISOString(),
            ]);

            return ApiResponse::success(
                data: [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => $user->status,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt->toISOString(),
                ],
                message: 'Google login successful'
            );
        } catch (\Exception $e) {
            Log::error('[ParentAuth] Google login error', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return ApiResponse::serverError('Failed to authenticate with Google: ' . $e->getMessage());
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return ApiResponse::success(
            data: [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'children' => $user->children()->select('id', 'student_id', 'full_name', 'campus_id')->get(),
            ],
            message: 'Parent profile retrieved successfully'
        );
    }

    public function getChildren(Request $request): JsonResponse
    {
        $user = $request->user();
        $children = $user->children()
            ->with(['campus:id,name,code', 'program:id,name,code'])
            ->select('id', 'student_id', 'full_name', 'email', 'phone', 'status', 'academic_status', 'campus_id', 'program_id')
            ->get();

        return ApiResponse::success(
            data: $children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'student_id' => $child->student_id,
                    'full_name' => $child->full_name,
                    'email' => $child->email,
                    'phone' => $child->phone,
                    'status' => $child->status,
                    'academic_status' => $child->academic_status,
                    'campus' => [
                        'id' => $child->campus?->id,
                        'name' => $child->campus?->name,
                        'code' => $child->campus?->code,
                    ],
                    'program' => [
                        'id' => $child->program?->id,
                        'name' => $child->program?->name,
                        'code' => $child->program?->code,
                    ],
                ];
            }),
            message: 'Children retrieved successfully'
        );
    }
}
