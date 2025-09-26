<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Services\EventNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private EventNotificationService $eventNotificationService
    ) {}

    /**
     * Get user's event notification preferences
     */
    public function getEventPreferences(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $this->eventNotificationService->getEventNotificationPreferences($user);

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user's event notification preferences
     */
    public function updateEventPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $request->validated()['preferences'];

            $this->eventNotificationService->updateEventNotificationPreferences($user, $preferences);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
