<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Notification\GetNotificationsAction;
use App\Actions\Notification\MarkAllNotificationsAsReadAction;
use App\Actions\Notification\MarkNotificationAsReadAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications for the authenticated user.
     */
    public function index(Request $request, GetNotificationsAction $action): JsonResponse
    {
        /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
        $notifications = $action->execute($request->user());
        
        $notifications->through(fn ($notification) => new NotificationResource($notification));
        
        return ApiResponse::paginated($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(
        Request $request, 
        string $notification, 
        MarkNotificationAsReadAction $action
    ): JsonResponse {
        $success = $action->execute($request->user(), $notification);
        
        if ($success) {
            return ApiResponse::success(null, [], 'Notification marked as read');
        }
        
        return ApiResponse::businessLogicError('Failed to mark notification as read');
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request, MarkAllNotificationsAsReadAction $action): JsonResponse
    {
        $count = $action->execute($request->user());
        
        return ApiResponse::success(['count' => $count], [], 'All notifications marked as read');
    }
}
