<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Notification\Http\Requests\Student\MarkNotificationsRequest;
use App\Modules\Notification\Http\Requests\Student\NotificationFilterRequest;
use App\Shared\Contracts\Notification\StudentNotificationReader;
use App\Shared\Contracts\Notification\StudentNotificationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student Notification Controller V2 - Uses NotificationMessage model.
 */
final class NotificationController extends Controller
{
    public function __construct(
        private readonly StudentNotificationReader $reader,
        private readonly StudentNotificationWriter $writer,
    ) {}

    /**
     * Get student's notifications.
     */
    public function index(NotificationFilterRequest $request): JsonResponse
    {
        $student = $request->user();

        $filters = $request->validated();
        /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
        $notifications = $this->reader->listForRecipient((int) $student->user_id, (int) $student->campus_id, $filters);
        $unreadCount = $this->reader->unreadCount((int) $student->user_id, (int) $student->campus_id);

        return ApiResponse::success(
            data: $notifications->items(),
            meta: [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'total_pages' => $notifications->lastPage(),
                'unread_count' => $unreadCount,
            ],
            message: 'Notifications retrieved successfully',
        );
    }

    /**
     * Get notification summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $student = $request->user();

        $summary = $this->reader->summaryForRecipient((int) $student->user_id, (int) $student->campus_id);

        return ApiResponse::success($summary, [], 'Notification summary retrieved successfully');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        $student = $request->user();

        $success = $this->writer->markAsRead((int) $student->user_id, (int) $student->campus_id, $notificationId);

        if ($success) {
            $unreadCount = $this->reader->unreadCount((int) $student->user_id, (int) $student->campus_id);

            return ApiResponse::success(['unread_count' => $unreadCount], [], 'Notification marked as read');
        }

        return ApiResponse::businessLogicError('Notification not found or already read');
    }

    /**
     * Mark multiple notifications as read.
     */
    public function markMultipleAsRead(MarkNotificationsRequest $request): JsonResponse
    {
        $student = $request->user();

        $notificationIds = $request->validated()['notification_ids'];
        $updated = $this->writer->markMultipleAsRead((int) $student->user_id, (int) $student->campus_id, $notificationIds);
        $unreadCount = $this->reader->unreadCount((int) $student->user_id, (int) $student->campus_id);

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
        $student = $request->user();

        $updated = $this->writer->markAllAsRead((int) $student->user_id, (int) $student->campus_id);
        $unreadCount = $this->reader->unreadCount((int) $student->user_id, (int) $student->campus_id);

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
        $student = $request->user();

        $success = $this->writer->archive((int) $student->user_id, (int) $student->campus_id, $notificationId);

        if ($success) {
            return ApiResponse::success(null, [], 'Notification archived successfully');
        }

        return ApiResponse::businessLogicError('Notification not found or already archived');
    }
}
