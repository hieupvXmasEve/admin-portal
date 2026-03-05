<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\MarkNotificationsRequest;
use App\Http\Requests\Api\V1\Student\NotificationFilterRequest;
use App\Http\Resources\Api\V1\Student\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\V1\Student\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student Notification Controller V2 - Uses NotificationMessage model.
 */
class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get student's notifications.
     */
    public function index(NotificationFilterRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $filters = $request->validated();
        /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
        $notifications = $this->notificationService->getNotifications($student, $filters);
        $unreadCount = $this->notificationService->getUnreadCount($student);

        $notifications->getCollection()->transform(fn ($msg) => (new NotificationResource($msg))->toArray($request));

        $response = ApiResponse::paginated($notifications, 'Notifications retrieved successfully');
        $responseData = $response->getData(true);
        $responseData['meta']['unread_count'] = $unreadCount;

        return response()->json($responseData, $response->getStatusCode());
    }

    /**
     * Get notification summary.
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $summary = $this->notificationService->getNotificationSummary($student);

        return ApiResponse::success($summary, [], 'Notification summary retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $success = $this->notificationService->markAsRead($student, $notificationId);

        if ($success) {
            $unreadCount = $this->notificationService->getUnreadCount($student);

            return ApiResponse::success(['unread_count' => $unreadCount], [], 'Notification marked as read');
        }

        return ApiResponse::businessLogicError('Notification not found or already read');
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(MarkNotificationsRequest $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $notificationIds = $request->validated()['notification_ids'];
        $updated = $this->notificationService->markMultipleAsRead($student, $notificationIds);
        $unreadCount = $this->notificationService->getUnreadCount($student);

        return ApiResponse::success(
            ['updated_count' => $updated, 'unread_count' => $unreadCount],
            [],
            "Marked {$updated} notifications as read"
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $updated = $this->notificationService->markAllAsRead($student);
        $unreadCount = $this->notificationService->getUnreadCount($student);

        return ApiResponse::success(
            ['updated_count' => $updated, 'unread_count' => $unreadCount],
            [],
            "Marked all {$updated} notifications as read"
        );
    }

    /**
     * Archive (delete) notification.
     */
    public function destroy(Request $request, int $notificationId): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();

        $success = $this->notificationService->deleteNotification($student, $notificationId);

        if ($success) {
            return ApiResponse::success(null, [], 'Notification archived successfully');
        }

        return ApiResponse::businessLogicError('Notification not found or already archived');
    }
}
