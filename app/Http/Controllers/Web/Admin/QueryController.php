<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Query\AssignQueryAction;
use App\Actions\Query\ReplyToQueryAction;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FormResponse;
use App\Models\QueryTicket;
use App\Models\User;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;

class QueryController extends Controller
{
    public function __construct(private UploadPlatform $imageUploadService)
    {
        $this->middleware('can:view_queries')->only('index');
        $this->middleware('can:detail_queries')->only(['show', 'assign', 'reply', 'updateStatus']);
    }

    public function index(Request $request)
    {
        $currentCampusId = app('campus')->id;
        $departments = $this->inboxDepartmentOptions();

        $query = FormResponse::query()
            ->where('campus_id', $currentCampusId)
            ->with(['form', 'student', 'assignedTo', 'queryTicket', 'formTarget'])
            ->whereHas('form', function ($q) {
                $q->where('type', 'query');
            });

        if ($request->filled('department_id')) {
            $deptId = (int) $request->input('department_id');

            if ($departments->contains('id', $deptId)) {
                $query->whereHas('formTarget', function ($q) use ($deptId) {
                    $q->where('scope_type', 'department')->where('scope_id', $deptId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('query_status', $request->input('status'));
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Forms/Queries/Inbox', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'department_id']),
            'statusOptions' => QueryTicket::STATUSES,
            'departments' => $departments,
        ]);
    }

    public function show(FormResponse $response)
    {
        $this->assertInboxResponse($response);

        /** @var User $user */
        $user = Auth::user();
        $response->load([
            'form',
            'student',
            'assignedTo',
            'answers.question.options',
            'answers.selectedOptions',
            'answers.attachments',
            'attachments',
            'queryTicket.topic',
            'queryTicket.replies.author',
            'queryTicket.replies.authorStudent',
            'queryTicket.replies.uploadRecord',
            'campus',
            'formTarget',
            'assignments.assignedBy',
            'assignments.fromAssignee',
            'assignments.toAssignee',
        ]);

        $departmentId = null;
        if ($response->formTarget && $response->formTarget->scope_type === 'department') {
            $departmentId = $response->formTarget->scope_id;
        }

        $canViewAll = $user->hasSystemRole('admin') ||
                      $user->hasSystemRole('super_admin') ||
                      $user->hasPermission('view_all_queries');

        $isHead = $departmentId ? $user->isDepartmentHead($departmentId) : false;

        $staffs = [];
        if ($departmentId && ($isHead || $canViewAll)) {
            $staffs = User::whereHas('memberships', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->where('is_active', true);
            })->get(['id', 'name']);
        }

        return Inertia::render('Forms/Queries/Detail', [
            'ticket' => $response,
            'staffs' => $staffs,
            'statusOptions' => QueryTicket::STATUSES,
            'canAssign' => $isHead || $canViewAll,
        ]);
    }

    public function assign(Request $request, FormResponse $response, AssignQueryAction $action)
    {
        $this->assertInboxResponse($response);

        $validated = $request->validate([
            'assigned_to_user_id' => 'nullable|string', // Changed to string to handle 'none'
            'note' => 'nullable|string|max:500',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $canViewAll = $user->hasSystemRole('admin') ||
                      $user->hasSystemRole('super_admin') ||
                      $user->hasPermission('view_all_queries');

        $departmentId = ($response->formTarget && $response->formTarget->scope_type === 'department')
            ? $response->formTarget->scope_id
            : null;
        $isHead = $departmentId ? $user->isDepartmentHead($departmentId) : false;

        if (! $canViewAll && ! $isHead) {
            abort(403, 'Only department heads or administrators can assign queries.');
        }

        $toAssigneeId = $validated['assigned_to_user_id'];
        if ($toAssigneeId === 'none' || empty($toAssigneeId)) {
            $toAssigneeId = null;
        } else {
            $toAssigneeId = (int) $toAssigneeId;
        }

        $action->execute(
            $response,
            $toAssigneeId,
            $user,
            $validated['note'] ?? null
        );

        return back()->with('success', 'Assignment updated successfully.');
    }

    public function updateStatus(Request $request, FormResponse $response)
    {
        $this->assertInboxResponse($response);

        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', QueryTicket::STATUSES),
        ]);

        $response->update(['query_status' => $validated['status']]);

        if ($response->queryTicket) {
            $response->queryTicket->update(['status' => $validated['status']]);
            // If ticket closed, update closed_at if exists
            if ($validated['status'] === 'closed') {
                $response->queryTicket->update(['closed_at' => now()]);
            }
        }

        return back()->with('success', 'Status updated successfully.');
    }

    public function reply(Request $request, FormResponse $response, ReplyToQueryAction $action)
    {
        $this->assertInboxResponse($response);

        $contextConfig = config('uploads.contexts.form_attachment', config('uploads.defaults'));
        $maxSize = (int) ($contextConfig['max_size'] ?? 10240);
        $allowedExtensions = $contextConfig['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];

        $validated = $request->validate([
            'message' => 'required|string|min:2',
            'is_official_answer' => 'sometimes|boolean',
            'set_pending' => 'sometimes|boolean',
            'attachment' => [
                'nullable',
                'file',
                File::types($allowedExtensions)->max($maxSize),
            ],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $uploadRecordId = null;
        if ($request->hasFile('attachment')) {
            $uploadRecord = $this->imageUploadService->upload(
                $request->file('attachment'),
                'form_attachment',
                $user->id
            );
            $uploadRecordId = $uploadRecord->id;
        }

        $action->execute(
            $response,
            $user,
            $validated['message'],
            (bool) ($validated['is_official_answer'] ?? true),
            $uploadRecordId,
            (bool) ($validated['set_pending'] ?? false)
        );

        return back()->with('success', 'Reply sent successfully.');
    }

    private function inboxDepartmentOptions()
    {
        return Department::query()
            ->where('is_active', true)
            ->whereIn('code', ['HQ', 'ACA'])
            ->orderByRaw("CASE code WHEN 'HQ' THEN 0 WHEN 'ACA' THEN 1 ELSE 2 END")
            ->get(['id', 'name', 'code']);
    }

    private function assertInboxResponse(FormResponse $response): void
    {
        $response->loadMissing(['form', 'formTarget']);

        abort_unless((int) $response->campus_id === (int) app('campus')->id, 404);
        abort_unless($response->form?->type === 'query', 404);
    }
}
