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
use Illuminate\Support\Facades\DB;
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
            ->when($request->input('search'), function ($query, $search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            })
            ->when($request->input('category'), function ($query, $category) {
                $query->where('category', $category);
            })
            ->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
                'per_page' => $request->input('per_page'),
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

        $programs = Program::query()->select('id', 'name')->get();

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
     * API to search recipients for the notification form.
     */
    public function searchTargets(Request $request)
    {
        $this->authorize('send_manual_notification');

        $type = $request->input('type', 'student');
        $search = $request->input('search');

        switch ($type) {
            case 'student':
                $results = Student::query()
                    ->active()
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%")
                                ->orWhere('student_id', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->when($request->input('program_id'), function ($query, $programId) {
                        $query->where('program_id', $programId);
                    })
                    ->limit(20)
                    ->get(['id', 'full_name as name', 'student_id as code', 'email']);
                break;

            case 'user':
                $results = \App\Models\User::query()
                    ->where('status', 'active')
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20)
                    ->get(['id', 'name', 'email']);
                break;

            case 'lecturer':
                $results = \App\Models\Lecture::query()
                    ->active()
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    })
                    ->limit(20)
                    ->get(['id', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'employee_id as code', 'email']);
                break;

            default:
                return \App\Http\Responses\ApiResponse::businessLogicError('Invalid target type');
        }

        return \App\Http\Responses\ApiResponse::success($results);
    }
}
