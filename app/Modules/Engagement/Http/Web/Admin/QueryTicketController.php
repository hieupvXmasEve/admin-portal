<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueryTicket;
use App\Modules\Engagement\Http\Requests\Support\StoreAdminQueryTicketReplyRequest;
use App\Modules\Engagement\Http\Requests\Support\UpdateQueryStatusRequest;
use App\Modules\Engagement\Http\Resources\QueryTicketResource;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Support\QueryTicketWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueryTicketController extends Controller
{
    public function __construct(protected QueryTicketWorkflow $queryTicketService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $campusId = (int) app('campus')->id;

        $perPage = (int) $request->input('per_page', 25);
        $perPage = min(max($perPage, 10), 100);

        $filters = $request->only(['status', 'question_id']);

        $tickets = $this->queryTicketService->getTicketsForCampus($user, $campusId, $filters, $perPage);

        $ticketsData = QueryTicketResource::collection(collect($tickets->items()))->resolve();

        $pagination = [
            'from' => $tickets->firstItem(),
            'to' => $tickets->lastItem(),
            'total' => $tickets->total(),
            'current_page' => $tickets->currentPage(),
            'last_page' => $tickets->lastPage(),
            'prev_page_url' => $tickets->previousPageUrl(),
            'next_page_url' => $tickets->nextPageUrl(),
            'per_page' => $tickets->perPage(),
            'links' => $tickets->linkCollection()->toArray(), // @phpstan-ignore-line
        ];

        // Get questions from active query forms
        $questions = collect();
        $queryForms = Form::where('type', 'query')
            ->where('status', 'active')
            ->get();

        foreach ($queryForms as $form) {
            $version = $form->latestPublishedVersion;
            if ($version) {
                $formQuestions = $version->questions()
                    ->select(['id', 'code', 'text'])
                    ->orderBy('order_index')
                    ->get();
                $questions = $questions->merge($formQuestions);
            }
        }

        $questions = $questions->unique('id')->map(fn ($question) => [
            'id' => $question->id,
            'text' => $question->text,
            'code' => $question->code,
        ])->values();

        return Inertia::render('Forms/Review/Queries/Index', [
            'tickets' => $ticketsData,
            'pagination' => $pagination,
            'filters' => [
                'status' => $filters['status'] ?? null,
                'question_id' => $filters['question_id'] ?? null,
                'per_page' => $tickets->perPage(),
            ],
            'statusOptions' => QueryTicket::STATUSES,
            'questions' => $questions,
        ]);
    }

    public function show(QueryTicket $ticket): Response
    {
        $this->assertTicketCampus($ticket);

        $ticket->load([
            'topic',
            'response' => function ($responseQuery) {
                $responseQuery->with([
                    'form.latestPublishedVersion.sections.questions.options',
                    'campus',
                    'student',
                    'answers.question',
                    'answers.selectedOptions',
                    'answers.attachments',
                    'attachments',
                ]);
            },
            'replies.author',
            'replies.authorStudent',
            'replies.uploadRecord',
        ]);

        return Inertia::render('Forms/Review/Queries/Show', [
            'ticket' => new QueryTicketResource($ticket),
            'statusOptions' => QueryTicket::STATUSES,
        ]);
    }

    public function storeReply(StoreAdminQueryTicketReplyRequest $request, QueryTicket $ticket): RedirectResponse
    {
        $this->assertTicketCampus($ticket);

        $validated = $request->validated();

        $reply = $this->queryTicketService->createReply(
            $ticket,
            $request->user(),
            $validated
        );

        return redirect()->back();
    }

    public function updateStatus(UpdateQueryStatusRequest $request, QueryTicket $ticket): RedirectResponse
    {
        $this->assertTicketCampus($ticket);

        $validated = $request->validated();

        $updatedTicket = $this->queryTicketService->updateStatus($ticket, $validated['status']);

        return redirect()
            ->route('forms.queries.show', $updatedTicket)
            ->with('success', 'Ticket status updated.');
    }

    private function assertTicketCampus(QueryTicket $ticket): void
    {
        $campusId = (int) app('campus')->id;

        abort_unless($ticket->response()->where('campus_id', $campusId)->exists(), 404);
    }
}
