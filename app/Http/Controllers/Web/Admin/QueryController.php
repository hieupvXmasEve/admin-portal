<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Query\AssignQueryAction;
use App\Actions\Query\ReplyToQueryAction;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FormResponse;
use App\Models\QueryTicket;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;

class QueryController extends Controller
{
    public function __construct(private ImageUploadService $imageUploadService)
    {
        $this->middleware('can:view_queries')->only('index');
        $this->middleware('can:detail_queries')->only(['show', 'assign', 'reply', 'updateStatus']);
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $currentCampusId = app('campus')->id;

        $query = FormResponse::query()
            ->where('campus_id', $currentCampusId)
            ->with(['form', 'student', 'assignedTo', 'queryTicket', 'formTarget'])
            ->whereHas('form', function ($q) {
                $q->where('type', 'query');
            });

        // Get user's active department IDs
        $userDeptIds = Department::whereHas('memberships', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('is_active', true);
        })->pluck('id')->toArray();

        // Check if user has permission to see everything or is an admin
        $canViewAll = $user->hasSystemRole('admin') || 
                      $user->hasSystemRole('super_admin') || 
                      $user->hasPermission('view_all_queries');

        // Filtering logic based on roles and selection
        if ($request->filled('department_id')) {
            $deptId = (int) $request->input('department_id');
            // Check if user is member of this dept or has view all access
            if (in_array($deptId, $userDeptIds) || $canViewAll) {
                $query->whereHas('formTarget', function ($q) use ($deptId) {
                    $q->where('scope_type', 'department')->where('scope_id', $deptId);
                });
                
                // If not "view all" or admin, restrict visibility within department:
                // Heads see all in dept, Staff see only assigned to them OR unassigned
                if (!$canViewAll && !$user->isDepartmentHead($deptId)) {
                     $query->where(function($q) use ($user) {
                         $q->where('assigned_to_user_id', $user->id)
                           ->orWhereNull('assigned_to_user_id');
                     });
                }
            } else {
                // Not a member and no global access -> see nothing for this dept
                $query->whereRaw('1 = 0');
            }
        } else {
            // Default View (No department chosen)
            if (!$canViewAll) {
                $query->where(function ($q) use ($user, $userDeptIds) {
                    // 1. Assigned to me
                    $q->where('assigned_to_user_id', $user->id);
                    // 2. Unassigned queries in my departments
                    if (!empty($userDeptIds)) {
                        $q->orWhere(function($sq) use ($userDeptIds) {
                            $sq->whereNull('assigned_to_user_id')
                               ->whereHas('formTarget', function($tq) use ($userDeptIds) {
                                   $tq->where('scope_type', 'department')
                                      ->whereIn('scope_id', $userDeptIds);
                               });
                        });
                    }
                });
            }
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('query_status', $request->input('status'));
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();

        // Fetch departments for the filter dropdown
        if ($canViewAll) {
            $userDepts = Department::where('is_active', true)->get(['id', 'name', 'code']);
        } else {
            $userDepts = Department::whereHas('memberships', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('is_active', true);
            })->get(['id', 'name', 'code']);
        }

        return Inertia::render('Forms/Queries/Inbox', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'department_id']),
            'statusOptions' => \App\Models\QueryTicket::STATUSES,
            'departments' => $userDepts,
        ]);
    }

    public function show(FormResponse $response)
    {
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
        
        // Authorization logic
        if (!$canViewAll && !$isHead) {
             $isMember = $departmentId ? $user->isDepartmentMember($departmentId) : false;
             $isAssignedToMe = $response->assigned_to_user_id === $user->id;
             $isUnassignedInMyDept = $isMember && $response->assigned_to_user_id === null;

             if (!$isAssignedToMe && !$isUnassignedInMyDept) {
                  abort(403, 'Unauthorized access to this query.');
             }
        }

        $staffs = [];
        if ($departmentId && ($isHead || $canViewAll)) {
            $staffs = User::whereHas('memberships', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)->where('is_active', true);
            })->get(['id', 'name']);
        }

        return Inertia::render('Forms/Queries/Detail', [
            'ticket' => $response,
            'staffs' => $staffs,
            'statusOptions' => \App\Models\QueryTicket::STATUSES,
            'canAssign' => $isHead || $canViewAll,
        ]);
    }

    public function assign(Request $request, FormResponse $response, AssignQueryAction $action)
    {
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

        if (!$canViewAll && !$isHead) {
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
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', \App\Models\QueryTicket::STATUSES)
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
        $isAssigned = $response->assigned_to_user_id === $user->id;

        if (!$canViewAll && !$isHead && !$isAssigned) {
            abort(403, 'Unauthorized to update this query status.');
        }

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
                File::types($allowedExtensions)->max($maxSize)
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
}
