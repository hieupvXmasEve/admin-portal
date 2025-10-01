<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\QueryReplyResource;
use App\Http\Resources\QueryTicketResource;
use App\Http\Responses\ApiResponse;
use App\Models\QueryTicket;
use App\Models\Student;
use App\Services\QueryTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class QueryTicketController extends Controller
{
    public function __construct(private QueryTicketService $queryTicketService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(QueryTicket::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $student = $this->resolveStudent($request);
        if (!$student) {
            return ApiResponse::authorizationError('Student not authenticated.');
        }

        $filters = [
            'status' => $request->input('status'),
        ];
        $perPage = (int) $request->input('per_page', 15);

        $tickets = $this->queryTicketService->getTicketsForStudent($student, $filters, $perPage);

        $pagination = [
            'from' => $tickets->firstItem(),
            'to' => $tickets->lastItem(),
            'total' => $tickets->total(),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'prev_page_url' => $tickets->previousPageUrl(),
            'next_page_url' => $tickets->nextPageUrl(),
            'per_page' => $tickets->perPage(),
        ];

        return ApiResponse::success(
            QueryTicketResource::collection($tickets->getCollection()),
            ['pagination' => $pagination],
            'Query tickets retrieved successfully.'
        );
    }

    public function show(Request $request, QueryTicket $ticket): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return ApiResponse::authorizationError('Student not authenticated.');
        }

        $ticket = $this->queryTicketService->getStudentTicket($student, $ticket);
        $ticket->load([
            'topic',
            'response' => function ($responseQuery) {
                $responseQuery->with([
                    'form.latestPublishedVersion.sections.questions.options',
                    'campus',
                    'answers.question',
                    'answers.attachments',
                    'attachments',
                ]);
            },
            'replies.author',
            'replies.authorStudent',
            'replies.uploadRecord',
        ]);

        return ApiResponse::success(
            new QueryTicketResource($ticket),
            [],
            'Query ticket retrieved successfully.'
        );
    }

    public function storeReply(Request $request, QueryTicket $ticket): JsonResponse
    {
        $contextConfig = config('uploads.contexts.form_attachment', config('uploads.defaults'));
        $maxSize = (int) ($contextConfig['max_size'] ?? config('uploads.defaults.max_size', 10240));
        $allowedExtensions = array_values(array_unique($contextConfig['allowed_extensions'] ?? []));
        $allowedMimeTypes = array_values(array_unique($contextConfig['allowed_types'] ?? []));

        if (empty($allowedExtensions)) {
            $allowedExtensions = array_values(array_unique(config('uploads.defaults.allowed_extensions', [])));
        }

        if (empty($allowedMimeTypes)) {
            $allowedMimeTypes = array_values(array_unique(config('uploads.defaults.allowed_types', [])));
        }

        $fileRule = !empty($allowedExtensions)
            ? File::types($allowedExtensions)->max($maxSize)
            : File::default()->max($maxSize);

        $attachmentRules = ['nullable', 'file', $fileRule];

        if (!empty($allowedExtensions)) {
            $attachmentRules[] = 'mimes:' . implode(',', $allowedExtensions);
        }

        if (!empty($allowedMimeTypes)) {
            $attachmentRules[] = 'mimetypes:' . implode(',', $allowedMimeTypes);
        }

        $request->validate([
            'message' => ['required', 'string'],
            'attachment' => $attachmentRules,
        ]);

        $student = $request->user();

        $ticket = $this->queryTicketService->getStudentTicket($student, $ticket);

        if ($ticket->status === QueryTicket::STATUS_CLOSED) {
            return ApiResponse::businessLogicError('This query has been closed and cannot be updated.');
        }

        $reply = $this->queryTicketService->createStudentReply(
            $ticket,
            $student,
            $request->only(['message', 'attachment'])
        );

        return ApiResponse::success(
            new QueryReplyResource($reply),
            [],
            'Reply submitted successfully.'
        );
    }

    private function resolveStudent(Request $request): ?Student
    {
        $student = Auth::guard('student')->user();

        if ($student instanceof Student) {
            return $student;
        }

        return null;
    }
}
