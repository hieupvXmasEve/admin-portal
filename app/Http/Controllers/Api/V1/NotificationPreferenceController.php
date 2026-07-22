<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notification\Actions\UpdateEventNotificationPreferencesAction;
use App\Modules\Notification\Queries\GetEventNotificationPreferencesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    /**
     * Get user's event notification preferences
     */
    public function getEventPreferences(Request $request, GetEventNotificationPreferencesQuery $query): JsonResponse
    {
        try {
            $user = Auth::user();
            $preferences = $query->handle($user);

            return response()->json([
                'success' => true,
                'data' => $preferences,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification preferences',
                'error' => $e->getMessage(),
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

            UpdateEventNotificationPreferencesAction::run($user, $preferences);

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification preferences',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
