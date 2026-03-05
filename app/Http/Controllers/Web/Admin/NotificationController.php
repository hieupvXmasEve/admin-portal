<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Notification\SendManualNotificationAction;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\SendManualNotificationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Modules\Notification\Actions\SendManualNotificationV2Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function sendForm(): Response
    {
        $this->authorize('send_manual_notification');

        $campusId = session('current_campus_id');

        $programs = Program::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Notifications/Send', [
            'categories' => NotificationCategory::options(),
            'programs' => $programs,
            'currentCampusId' => $campusId,
        ]);
    }

    public function send(
        SendManualNotificationRequest $request,
        SendManualNotificationAction $legacyAction,
        SendManualNotificationV2Action $v2Action
    ): \Illuminate\Http\JsonResponse {
        $data = $request->validated();

        if (config('notification.write_mode') === 'v2') {
            $eventId = $v2Action->run($data);

            return ApiResponse::success(
                ['event_id' => $eventId],
                [],
                'Notification queued for delivery.'
            );
        }

        $legacyAction->execute($data);

        return ApiResponse::success(
            null,
            [],
            'Notification sent successfully.'
        );
    }

    public function searchTargets(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('send_manual_notification');

        $type = $request->input('type', 'student');
        $search = $request->input('search');
        $campusId = $request->input('campus_id', session('current_campus_id'));

        switch ($type) {
            case 'student':
                $results = Student::query()
                    ->active()
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
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
                $results = User::query()
                    ->where('status', 'active')
                    ->when($campusId, fn($q) => $q->whereHas(
                        'campusUserRoles',
                        fn($sub) => $sub->where('campus_id', $campusId)
                    ))
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
                $results = Lecture::query()
                    ->active()
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
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
                return ApiResponse::businessLogicError('Invalid target type');
        }

        return ApiResponse::success($results);
    }
}
