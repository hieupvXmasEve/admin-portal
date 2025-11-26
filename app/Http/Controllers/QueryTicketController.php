<?php

namespace App\Http\Controllers;

use App\Http\Resources\QueryTicketResource;
use App\Models\Campus;
use App\Models\Form;
use App\Models\QueryTicket;
use App\Services\QueryTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class QueryTicketController extends Controller
{
    public function __construct(protected QueryTicketService $queryTicketService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $campus = Campus::findOrFail(app('campus')->id);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = min(max($perPage, 10), 100);

        $filters = $request->only(['status', 'question_id']);

        $tickets = $this->queryTicketService->getTicketsForCampus($user, $campus, $filters, $perPage);

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

        $questions = $questions->unique('id')->map(fn($question) => [
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

    public function storeReply(Request $request, QueryTicket $ticket): RedirectResponse
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

        $validated = $request->validate([
            'message' => ['required', 'string'],
            'is_official_answer' => ['sometimes', 'boolean'],
            'set_pending' => ['sometimes', 'boolean'],
            'attachment' => $attachmentRules,
        ]);

        $reply = $this->queryTicketService->createReply(
            $ticket,
            $request->user(),
            $validated
        );

        return redirect()->back();
    }

    public function updateStatus(Request $request, QueryTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(QueryTicket::STATUSES)],
        ]);

        $updatedTicket = $this->queryTicketService->updateStatus($ticket, $validated['status']);

        return redirect()
            ->route('forms.queries.show', $updatedTicket)
            ->with('success', 'Ticket status updated.');
    }
}
