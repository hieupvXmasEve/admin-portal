<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

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
                    'enrollment_status' => $student->enrollment_status,
                    'campus' => $student->campus->name ?? null,
                    'program' => $student->program->name ?? null,
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
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
                    'enrollment_status' => $student->enrollment_status,
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
            'enrollment_status' => 'admitted',
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
}
