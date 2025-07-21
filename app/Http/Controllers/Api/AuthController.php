<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Lecture;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Student login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $student = Student::where('email', $request->email)->first();

        if (!$student || !Hash::check($request->password, $student->password ?? '')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if student account is active
        if ($student->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active'
            ], 403);
        }

        // Update last login
        $student->update(['last_login_at' => now()]);

        // Create token
        $deviceName = $request->device_name ?? 'Student Portal';
        $token = $student->createToken($deviceName, ['student'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                    'campus' => $student->campus->name ?? null,
                    'program' => $student->program->name ?? null,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Unified Google OAuth Login (Students and Lecturers)
     */
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate Google access token
            $response = Http::withToken($request->access_token)
                ->get('https://www.googleapis.com/oauth2/v1/userinfo');

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google access token'
                ], 401);
            }

            $googleUserData = $response->json();

            if (!$googleUserData || !isset($googleUserData['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to retrieve user information from Google'
                ], 401);
            }

            // Check for lecturer account first
            $lecturer = Lecture::where('email', $googleUserData['email'])->first();
            
            if ($lecturer) {
                return $this->handleLecturerGoogleLogin($lecturer, $googleUserData, $request->device_name);
            }

            // Check for student account
            $student = Student::where('email', $googleUserData['email'])->first();
            
            if ($student) {
                return $this->handleStudentGoogleLogin($student, $googleUserData, $request->device_name);
            }

            // No account found - handle student registration if allowed
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

                return response()->json([
                    'success' => true,
                    'message' => 'Student account created successfully. Please wait for admin approval.',
                    'data' => [
                        'user_type' => 'student',
                        'student' => [
                            'id' => $student->id,
                            'full_name' => $student->full_name,
                            'email' => $student->email,
                            'status' => $student->status,
                        ]
                    ]
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => 'No account found with this email. Please contact your administrator.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to authenticate with Google: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Student logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Get authenticated student
     */
    public function me(Request $request): JsonResponse
    {
        $student = $request->user()->load(['campus', 'program', 'specialization']);

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                    'gender' => $student->gender,
                    'nationality' => $student->nationality,
                    'address' => $student->address,
                    'avatar_url' => $student->avatar_url,
                    'status' => $student->status,
                    'admission_date' => $student->admission_date?->format('Y-m-d'),
                    'expected_graduation_date' => $student->expected_graduation_date?->format('Y-m-d'),
                    'campus' => $student->campus ? [
                        'id' => $student->campus->id,
                        'name' => $student->campus->name,
                        'code' => $student->campus->code,
                    ] : null,
                    'program' => $student->program ? [
                        'id' => $student->program->id,
                        'name' => $student->program->name,
                        'code' => $student->program->code ?? null,
                    ] : null,
                    'specialization' => $student->specialization ? [
                        'id' => $student->specialization->id,
                        'name' => $student->specialization->name,
                    ] : null,
                    'can_register' => $student->canRegisterForCourses(),
                    'has_active_holds' => $student->hasActiveHolds(),
                ]
            ]
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $student = $request->user();
        $currentToken = $request->user()->currentAccessToken();

        // Delete current token
        $currentToken->delete();

        // Create new token
        $deviceName = $currentToken->name ?? 'Student Portal';
        $newToken = $student->createToken($deviceName, ['student'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Register new student (if enabled)
     */
    public function register(Request $request): JsonResponse
    {
        // This endpoint might be disabled in production
        // Only allow registration if explicitly enabled
        if (!config('app.allow_student_registration', false)) {
            return response()->json([
                'success' => false,
                'message' => 'Student registration is not available'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create student with minimal information
        // Admin will need to complete the profile later
        $student = Student::create([
            'full_name' => $request->full_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'status' => 'inactive', // Requires admin activation
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please wait for admin approval.',
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                ]
            ]
        ], 201);
    }

    /**
     * Forgot password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:students,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement password reset email sending
        // This would typically send a password reset link to the student's email

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email'
        ]);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:students,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement password reset token validation
        // This would validate the reset token and update the password

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful'
        ]);
    }

    /**
     * Handle Google login for students
     */
    private function handleStudentGoogleLogin(Student $student, array $googleUserData, ?string $deviceName): JsonResponse
    {
        // Update OAuth provider data if not set
        if (!$student->oauth_provider_id) {
            $student->update([
                'oauth_provider' => 'google',
                'oauth_provider_id' => $googleUserData['id'],
                'avatar_url' => $student->avatar_url ?: ($googleUserData['picture'] ?? null),
                'email_verified_at' => $student->email_verified_at ?: now(),
            ]);
        }

        // Check if student account is active
        if ($student->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Student account is not active'
            ], 403);
        }

        // Update last login
        $student->update(['last_login_at' => now()]);

        // Create token
        $deviceName = $deviceName ?? 'Student Portal (Google)';
        $token = $student->createToken($deviceName, ['student'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Google login successful',
            'data' => [
                'user_type' => 'student',
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                    'campus' => $student->campus->name ?? null,
                    'program' => $student->program->name ?? null,
                    'avatar_url' => $student->avatar_url,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Handle Google login for lecturers
     */
    private function handleLecturerGoogleLogin(Lecture $lecturer, array $googleUserData, ?string $deviceName): JsonResponse
    {
        // Update OAuth provider data if not set
        if (!$lecturer->oauth_provider_id) {
            $lecturer->update([
                'oauth_provider' => 'google',
                'oauth_provider_id' => $googleUserData['id'],
                'avatar_url' => $lecturer->avatar_url ?: ($googleUserData['picture'] ?? null),
                'email_verified_at' => $lecturer->email_verified_at ?: now(),
            ]);
        }

        // Check if lecturer account is active
        if (!$lecturer->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Lecturer account is not active'
            ], 403);
        }

        // Update last login
        $lecturer->update(['last_login_at' => now()]);

        // Create token
        $deviceName = $deviceName ?? 'Lecturer Portal (Google)';
        $token = $lecturer->createToken($deviceName, ['lecturer'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Google login successful',
            'data' => [
                'user_type' => 'lecturer',
                'lecturer' => [
                    'id' => $lecturer->id,
                    'employee_id' => $lecturer->employee_id,
                    'full_name' => $lecturer->full_name,
                    'display_name' => $lecturer->display_name,
                    'email' => $lecturer->email,
                    'academic_rank' => $lecturer->academic_rank,
                    'academic_rank_label' => $lecturer->getAcademicRankLabel(),
                    'employment_status' => $lecturer->employment_status,
                    'employment_status_label' => $lecturer->getEmploymentStatusLabel(),
                    'campus' => $lecturer->campus->name ?? null,
                    'department' => $lecturer->department,
                    'faculty' => $lecturer->faculty,
                    'avatar_url' => $lecturer->avatar_url,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }


    /**
     * Get authenticated lecturer
     */
    public function lecturerMe(Request $request): JsonResponse
    {
        $lecturer = $request->user()->load(['campus']);

        return response()->json([
            'success' => true,
            'data' => [
                'lecturer' => [
                    'id' => $lecturer->id,
                    'employee_id' => $lecturer->employee_id,
                    'title' => $lecturer->title,
                    'first_name' => $lecturer->first_name,
                    'last_name' => $lecturer->last_name,
                    'full_name' => $lecturer->full_name,
                    'display_name' => $lecturer->display_name,
                    'email' => $lecturer->email,
                    'phone' => $lecturer->phone,
                    'mobile_phone' => $lecturer->mobile_phone,
                    'avatar_url' => $lecturer->avatar_url,
                    'academic_rank' => $lecturer->academic_rank,
                    'academic_rank_label' => $lecturer->getAcademicRankLabel(),
                    'employment_status' => $lecturer->employment_status,
                    'employment_status_label' => $lecturer->getEmploymentStatusLabel(),
                    'employment_type' => $lecturer->employment_type,
                    'department' => $lecturer->department,
                    'faculty' => $lecturer->faculty,
                    'specialization' => $lecturer->specialization,
                    'expertise_areas' => $lecturer->expertise_areas,
                    'highest_degree' => $lecturer->highest_degree,
                    'degree_field' => $lecturer->degree_field,
                    'alma_mater' => $lecturer->alma_mater,
                    'hire_date' => $lecturer->hire_date?->format('Y-m-d'),
                    'years_of_service' => $lecturer->years_of_service,
                    'campus' => $lecturer->campus ? [
                        'id' => $lecturer->campus->id,
                        'name' => $lecturer->campus->name,
                        'code' => $lecturer->campus->code,
                    ] : null,
                    'office_address' => $lecturer->office_address,
                    'office_phone' => $lecturer->office_phone,
                    'can_teach_online' => $lecturer->can_teach_online,
                    'teaching_modalities' => $lecturer->teaching_modalities,
                    'preferred_teaching_days' => $lecturer->preferred_teaching_days,
                    'max_teaching_hours_per_week' => $lecturer->max_teaching_hours_per_week,
                    'current_course_load' => $lecturer->getCurrentCourseLoad(),
                    'is_available_for_assignment' => $lecturer->is_available_for_assignment,
                ]
            ]
        ]);
    }

    /**
     * Lecturer logout
     */
    public function lecturerLogout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lecturer logout successful'
        ]);
    }
}
