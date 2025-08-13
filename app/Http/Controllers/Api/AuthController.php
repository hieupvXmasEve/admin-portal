<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password ?? '')) {
            return ApiResponse::authenticationError('Invalid credentials');
        }

        // Check if student account is active
        if ($user->status !== 'active') {
            return ApiResponse::authorizationError('Account is not active');
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Create token
        $deviceName = $request->device_name ?? 'Student Portal';
        $token = $user->createToken($deviceName, ['student'])->plainTextToken;

        return ApiResponse::success(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'campus' => $user->campuses() ?? null,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            message: 'Login successful'
        );
    }

    /**
     * Student logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(
            data: null,
            message: 'Logout successful'
        );
    }

    /**
     * Get authenticated student
     */
    public function me(Request $request): JsonResponse
    {
        $student = $request->user()->load(['campus', 'program', 'specialization']);

        return ApiResponse::success(
            data: [
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
                ],
            ],
            message: 'Profile retrieved successfully'
        );
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

        return ApiResponse::success(
            data: [
                'token' => $newToken,
                'token_type' => 'Bearer',
            ],
            message: 'Token refreshed successfully'
        );
    }

    /**
     * Register new student (if enabled)
     */
    public function register(Request $request): JsonResponse
    {
        // This endpoint might be disabled in production
        // Only allow registration if explicitly enabled
        if (! config('app.allow_student_registration', false)) {
            return ApiResponse::authorizationError('Student registration is not available');
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|unique:students,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors()->toArray());
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

        return ApiResponse::success(
            data: [
                'student' => [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'status' => $student->status,
                ],
            ],
            message: 'Registration successful. Please wait for admin approval.',
            status: 201
        );
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
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TODO: Implement password reset email sending
        // This would typically send a password reset link to the student's email

        return ApiResponse::success(
            data: null,
            message: 'Password reset link sent to your email'
        );
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
            return ApiResponse::validationError($validator->errors()->toArray());
        }

        // TODO: Implement password reset token validation
        // This would validate the reset token and update the password

        return ApiResponse::success(
            data: null,
            message: 'Password reset successful'
        );
    }

    /**
     * Get authenticated lecturer
     */
    public function lecturerMe(Request $request): JsonResponse
    {
        $lecturer = $request->user()->load(['campus']);

        return ApiResponse::success(
            data: [
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
                ],
            ],
            message: 'Profile retrieved successfully'
        );
    }

    /**
     * Lecturer logout
     */
    public function lecturerLogout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(
            data: null,
            message: 'Lecturer logout successful'
        );
    }
}
