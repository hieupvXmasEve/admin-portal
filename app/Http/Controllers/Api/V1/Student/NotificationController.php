<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\MarkNotificationsRequest;
use App\Http\Requests\Api\V1\Student\NotificationFilterRequest;
use App\Http\Requests\Api\V1\Student\NotificationPreferenceRequest;
use App\Http\Resources\Api\V1\Student\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get student's notifications
     */
    public function index(NotificationFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $filters = $request->validated();
            $notifications = $this->notificationService->getNotifications($student, $filters);

            return ApiResponse::success(
                new NotificationResource($notifications),
                'Notifications retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve notifications');
        }
    }

    /**
     * Get notification summary
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $summary = $this->notificationService->getNotificationSummary($student);

            return ApiResponse::success(
                $summary,
                'Notification summary retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve notification summary');
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $success = $this->notificationService->markAsRead($student, $notificationId);

            if ($success) {
                return ApiResponse::success(
                    null,
                    'Notification marked as read'
                );
            } else {
                return ApiResponse::businessLogicError('Failed to mark notification as read');
            }
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Mark multiple notifications as read
     */
    public function markMultipleAsRead(MarkNotificationsRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $notificationIds = $request->validated()['notification_ids'];
            $updated = $this->notificationService->markMultipleAsRead($student, $notificationIds);

            return ApiResponse::success(
                ['updated_count' => $updated],
                "Marked {$updated} notifications as read"
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to mark notifications as read');
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $updated = $this->notificationService->markAllAsRead($student);

            return ApiResponse::success(
                ['updated_count' => $updated],
                "Marked all {$updated} notifications as read"
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to mark all notifications as read');
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $success = $this->notificationService->deleteNotification($student, $notificationId);

            if ($success) {
                return ApiResponse::success(
                    null,
                    'Notification deleted successfully'
                );
            } else {
                return ApiResponse::businessLogicError('Failed to delete notification');
            }
        } catch (\Exception $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    /**
     * Get notification preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $preferences = $this->notificationService->getNotificationPreferences($student);

            return ApiResponse::success(
                $preferences,
                'Notification preferences retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to retrieve notification preferences');
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(NotificationPreferenceRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        try {
            $preferences = $request->validated()['preferences'];
            $success = $this->notificationService->updateNotificationPreferences($student, $preferences);

            if ($success) {
                return ApiResponse::success(
                    null,
                    'Notification preferences updated successfully'
                );
            } else {
                return ApiResponse::businessLogicError('Failed to update notification preferences');
            }
        } catch (\Exception $e) {
            return ApiResponse::serverError('Failed to update notification preferences');
        }
    }
}
