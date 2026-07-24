<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Notification\Http\Requests\Student\EventNotificationPreferencesRequest;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesReader;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class NotificationPreferenceController extends Controller
{
    /**
     * Get user's event notification preferences
     */
    public function getEventPreferences(Request $request, StudentEventNotificationPreferencesReader $preferences): JsonResponse
    {
        try {
            $data = $preferences->forUser((int) $request->user()->user_id);

            return ApiResponse::compatible([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible([
                'success' => false,
                'message' => 'Failed to retrieve notification preferences',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update user's event notification preferences
     */
    public function updateEventPreferences(
        EventNotificationPreferencesRequest $request,
        StudentEventNotificationPreferencesWriter $preferences,
    ): JsonResponse
    {
        try {
            $preferences->updateForUser((int) $request->user()->user_id, $request->validated()['preferences']);

            return ApiResponse::compatible([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
            ]);
        } catch (\Throwable $exception) {
            return ApiResponse::compatible([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
