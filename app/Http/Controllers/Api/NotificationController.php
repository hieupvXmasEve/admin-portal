<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications for the authenticated user (V2).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $campusId = Auth::guard('web')->check()
            ? (int) session('current_campus_id', 0)
            : ($user->campus_id ?? 0);

        $notifications = NotificationMessage::query()
            ->where('recipient_user_id', $user->id)
            ->when($campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        $notifications->through(fn (NotificationMessage $msg) => [
            'id' => $msg->id,
            'title' => $msg->title,
            'message' => $msg->body,
            'data' => $msg->data,
            'type_key' => $msg->type_key,
            'event_name' => $msg->event_name,
            'read_at' => $msg->read_at?->toISOString(),
            'created_at' => $msg->created_at?->toISOString(),
        ]);

        return ApiResponse::paginated($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, int $notification): JsonResponse
    {
        $user = $request->user();

        $updated = NotificationMessage::query()
            ->where('id', $notification)
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            return ApiResponse::success(null, [], 'Notification marked as read');
        }

        return ApiResponse::businessLogicError('Failed to mark notification as read');
    }

    /**
     * Mark all notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $campusId = Auth::guard('web')->check()
            ? (int) session('current_campus_id', 0)
            : ($user->campus_id ?? 0);

        $count = NotificationMessage::query()
            ->where('recipient_user_id', $user->id)
            ->when($campusId > 0, fn ($q) => $q->where('campus_id', $campusId))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(['count' => $count], [], 'All notifications marked as read');
    }
}
