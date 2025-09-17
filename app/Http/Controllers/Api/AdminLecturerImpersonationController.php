<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AdminLecturerImpersonationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminLecturerImpersonationController extends Controller
{
    /**
     * Generate an access token for a lecturer without requiring their credentials.
     * This endpoint is designed specifically for admin impersonation functionality.
     */
    public function impersonateLecturer(AdminLecturerImpersonationRequest $request): JsonResponse
    {
        try {
            // Get the authenticated admin user
            $admin = Auth::user();

            // Find the lecturer
            $lecturer = Lecture::where('email', $request->email)
                ->orWhere('employee_id', $request->email) // Allow lookup by employee_id as well
                ->first();

            if (!$lecturer) {
                return ApiResponse::notFound('Lecturer not found with the provided email or employee ID.');
            }

            // Check if lecturer is active and can be impersonated
            if (!$lecturer->isActive()) {
                return ApiResponse::validationError(
                    [
                        'Cannot impersonate inactive lecturer. Lecturer status: ' . $lecturer->employment_status
                    ]
                );
            }

            // Create impersonation token
            $deviceName = $request->device_name ?? 'Admin Impersonation';
            $expiresAt = now()->addHours(2); // Shorter expiration for impersonation tokens

            $token = $lecturer->createToken($deviceName, ['lecturer'], $expiresAt)->plainTextToken;

            // Log the impersonation activity
            Log::info('Admin lecturer impersonation', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'lecturer_id' => $lecturer->id,
                'lecturer_email' => $lecturer->email,
                'lecturer_employee_id' => $lecturer->employee_id,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->toISOString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return ApiResponse::success(
                data: [
                    'lecturer' => [
                        'id' => $lecturer->id,
                        'employee_id' => $lecturer->employee_id,
                        'display_name' => $lecturer->display_name,
                        'email' => $lecturer->email,
                        'employment_status' => $lecturer->employment_status,
                        'academic_rank' => $lecturer->academic_rank,
                        'campus' => $lecturer->campus?->name,
                        'avatar_url' => $lecturer->avatar_url,
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
                message: 'Lecturer impersonation token generated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Admin lecturer impersonation failed', [
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
            Log::error('Failed to get lecturer impersonation sessions', [
                'admin_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve impersonation sessions');
        }
    }
}
