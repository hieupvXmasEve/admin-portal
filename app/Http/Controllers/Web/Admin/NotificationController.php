<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Notification\SendManualNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\SendManualNotificationRequest;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Student;
use App\Enums\NotificationCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of the notification history.
     */
    public function index(Request $request): Response
    {
        $this->authorize('view_any_notification');

        $notifications = Notification::query()
            ->with(['notifiable'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            })
            ->when($request->category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
            ->paginate($request->per_page ?? 15)
            ->withQueryString();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'sort' => $request->sort,
                'direction' => $request->direction,
                'per_page' => $request->per_page,
            ],
            'categories' => NotificationCategory::options(),
        ]);
    }

    /**
     * Show the form for sending a manual notification.
     */
    public function sendForm(): Response
    {
        $this->authorize('send_manual_notification');

        $programs = Program::select('id', 'name')->get();

        return Inertia::render('Admin/Notifications/Send', [
            'categories' => NotificationCategory::options(),
            'programs' => $programs,
        ]);
    }

    /**
     * Send a manual notification.
     */
    public function send(
        SendManualNotificationRequest $request,
        SendManualNotificationAction $action
    ) {
        $action->execute($request->validated());

        return \App\Http\Responses\ApiResponse::success(null, [], 'Notification sent successfully.');
    }

    /**
     * API to search students for the notification form.
     */
    public function searchStudents(Request $request)
    {
        $this->authorize('send_manual_notification');

        $students = Student::query()
            ->active()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->program_id, function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->limit(20)
            ->get(['id', 'full_name', 'student_id', 'email']);

        return \App\Http\Responses\ApiResponse::success($students);
    }
}
