<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Engagement\Actions\Support\CreateStudentQueryReplyAction;
use App\Modules\Engagement\Http\Requests\Support\ListStudentQueryTicketsRequest;
use App\Modules\Engagement\Http\Requests\Support\StoreQueryReplyRequest;
use App\Modules\Engagement\Http\Resources\QueryReplyResource;
use App\Modules\Engagement\Http\Resources\QueryTicketResource;
use App\Modules\Engagement\Models\QueryTicket;
use App\Modules\Engagement\Support\QueryTicketWorkflow;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Contracts\Upload\UploadRecordReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QueryTicketController extends Controller
{
    public function __construct(
        private QueryTicketWorkflow $queryTicketService,
        private StudentReferenceReader $students,
        private UploadRecordReader $uploadRecordReader,
    ) {}

    public function index(ListStudentQueryTicketsRequest $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (! $student) {
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
        if (! $student) {
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
                    'answers.selectedOptions',
                    'answers.attachments',
                ]);
            },
            'replies.author',
            'replies.authorStudent',
        ]);

        if ($ticket->response) {
            $ticket->response->setRelation(
                'attachments',
                collect($this->uploadRecordReader->byResponseIds([$ticket->response->id])[$ticket->response->id] ?? [])
            );
        }

        $replySummaries = $this->uploadRecordReader->byReplyIds($ticket->replies->pluck('id')->all());
        foreach ($ticket->replies as $reply) {
            $reply->setRelation('uploadRecord', $replySummaries[$reply->id] ?? null);
        }

        return ApiResponse::success(
            new QueryTicketResource($ticket),
            [],
            'Query ticket retrieved successfully.'
        );
    }

    public function storeReply(
        StoreQueryReplyRequest $request,
        QueryTicket $ticket,
        CreateStudentQueryReplyAction $action
    ): JsonResponse {
        $student = $this->resolveStudent($request);
        if (! $student) {
            return ApiResponse::authorizationError('Student not authenticated.');
        }

        $ticket = $this->queryTicketService->getStudentTicket($student, $ticket);

        try {
            $studentReference = $this->students->find((int) $student->id);
            if ($studentReference === null) {
                return ApiResponse::authenticationError('Student not found.');
            }

            $reply = $action->execute($ticket, $studentReference, $request->validated());

            return ApiResponse::success(
                new QueryReplyResource($reply),
                [],
                'Reply submitted successfully.'
            );
        } catch (ValidationException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }
    }

    private function resolveStudent(Request $request): mixed
    {
        $actor = $request->user();

        return is_object($actor)
            && filled($actor->student_id)
            && filled($actor->campus_id)
            ? $actor
            : null;
    }
}
