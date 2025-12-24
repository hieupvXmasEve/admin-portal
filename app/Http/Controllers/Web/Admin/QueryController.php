<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Query\AssignQueryTicketAction;
use App\Actions\Query\ReplyToQueryAction;
use App\Http\Controllers\Controller;
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
            ->with(['form', 'student', 'assignedTo', 'queryTicket'])
            ->whereHas('form', function ($q) {
                $q->where('type', 'query');
            });

        // Visibility Logic: Admin/Head sees all, Staff sees assigned
        if (!$user->can('view_form')) {
            $query->where('assigned_to_user_id', $user->id);
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('query_status', $request->input('status'));
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Forms/Queries/Inbox', [
            'tickets' => $tickets,
            'filters' => $request->only(['status']),
            'statusOptions' => QueryTicket::STATUSES,
        ]);
    }

    public function show(FormResponse $response)
    {
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
            'campus'
        ]);

        return Inertia::render('Forms/Queries/Detail', [
            'ticket' => $response,
            'staffs' => User::limit(50)->get(['id', 'name']), 
            'statusOptions' => QueryTicket::STATUSES,
        ]);
    }

    public function assign(Request $request, FormResponse $response, AssignQueryTicketAction $action)
    {
        $validated = $request->validate([
            'assigned_to_user_id' => 'required|exists:users,id'
        ]);

        $assignee = User::findOrFail($validated['assigned_to_user_id']);
        
        $action->execute($response, $assignee, Auth::user());

        return back()->with('success', 'Assigned successfully.');
    }

    public function updateStatus(Request $request, FormResponse $response)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', QueryTicket::STATUSES)
        ]);

        $response->update(['query_status' => $validated['status']]);
        
        if ($response->queryTicket) {
            $response->queryTicket->update(['status' => $validated['status']]);
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
