<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminStudentImpersonationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminStudentImpersonationController extends Controller
{
    /**
     * Generate an access token for a student without requiring their credentials.
     * This endpoint is designed specifically for admin impersonation functionality.
     */
    public function impersonateStudent(AdminStudentImpersonationRequest $request): JsonResponse
    {
        try {
            // Get the authenticated admin user
            $admin = Auth::user();
            
            // Find the student
            $student = Student::where('email', $request->email)
                ->orWhere('student_id', $request->email) // Allow lookup by student_id as well
                ->first();

            if (!$student) {
                return ApiResponse::notFound('Student not found with the provided email or student ID.');
            }

            // Check if student is active and can be impersonated
            if (!$student->isActive()) {
                return ApiResponse::validationError(
                    'Cannot impersonate inactive student. Student status: ' . $student->status
                );
            }

            // Check for blocking academic holds
            $blockingHolds = $student->academicHolds()
                ->where('status', 'active')
                ->where('hold_category', 'all')
                ->exists();

            if ($blockingHolds) {
                return ApiResponse::validationError(
                    'Cannot impersonate student due to active academic holds that restrict access.'
                );
            }

            // Create impersonation token
            $deviceName = $request->device_name ?? 'Admin Impersonation';
            $expiresAt = now()->addHours(2); // Shorter expiration for impersonation tokens
            
            $token = $student->createToken($deviceName, ['student'], $expiresAt)->plainTextToken;

            // Log the impersonation activity
            Log::info('Admin student impersonation', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'student_id' => $student->id,
                'student_email' => $student->email,
                'student_student_id' => $student->student_id,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->toISOString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
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
                    'impersonation_info' => [
                        'impersonated_by' => [
                            'id' => $admin->id,
                            'name' => $admin->name,
                            'email' => $admin->email,
                        ],
                        'impersonated_at' => now()->toISOString(),
                        'purpose' => $request->purpose ?? 'Admin support',
                    ],
                ],
                message: 'Student impersonation token generated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Admin student impersonation failed', [
                'admin_id' => Auth::id(),
                'request_email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Failed to generate impersonation token');
        }
    }

    /**
     * Get information about current impersonation sessions.
     * Useful for audit and monitoring purposes.
     */
    public function getImpersonationSessions(): JsonResponse
    {
        try {
            $admin = Auth::user();
            
            // Get recent impersonation activities for this admin
            // This would typically query a dedicated impersonation log table
            // For now, we'll return a placeholder response
            
            return ApiResponse::success(
                data: [
                    'active_sessions' => [],
                    'recent_impersonations' => [],
                    'total_impersonations_today' => 0,
                ],
                message: 'Impersonation sessions retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to get impersonation sessions', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve impersonation sessions');
        }
    }
}