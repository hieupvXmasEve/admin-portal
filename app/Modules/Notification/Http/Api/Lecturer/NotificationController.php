<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Api\Lecturer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Notification\Http\Requests\Lecturer\NotificationFilterRequest;
use App\Shared\Contracts\Identity\LecturerTeachingActor;
use App\Shared\Contracts\Notification\LecturerNotificationReader;
use App\Shared\Contracts\Notification\LecturerNotificationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly LecturerNotificationReader $reader,
        private readonly LecturerNotificationWriter $writer,
    ) {}

    public function index(NotificationFilterRequest $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();
        $filters = $request->validated();
        $notifications = $this->reader->listForRecipient($lecturer->lecturerUserId(), $lecturer->lecturerCampusId(), $filters);

        return ApiResponse::success(
            data: $notifications->items(),
            meta: [
                'page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'total_pages' => $notifications->lastPage(),
                'unread_count' => $this->reader->unreadCount($lecturer->lecturerUserId(), $lecturer->lecturerCampusId()),
            ],
            message: 'Notifications retrieved successfully',
        );
    }

    public function markAsRead(Request $request, int $notificationId): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();
        $updated = $this->writer->markAsRead($lecturer->lecturerUserId(), $lecturer->lecturerCampusId(), $notificationId);

        if (! $updated) {
            return ApiResponse::businessLogicError('Notification not found or already read');
        }

        return ApiResponse::success(
            ['unread_count' => $this->reader->unreadCount($lecturer->lecturerUserId(), $lecturer->lecturerCampusId())],
            [],
            'Notification marked as read',
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var LecturerTeachingActor $lecturer */
        $lecturer = $request->user();
        $updated = $this->writer->markAllAsRead($lecturer->lecturerUserId(), $lecturer->lecturerCampusId());

        return ApiResponse::success(
            ['updated_count' => $updated, 'unread_count' => 0],
            [],
            "Marked all {$updated} notifications as read",
        );
    }
}
