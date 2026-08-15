<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Models\Student;
use App\Models\User;
use App\Modules\Engagement\Models\QueryReply;
use App\Modules\Engagement\Models\QueryTicket;
use App\Shared\Contracts\Upload\FileUploadGateway;
use App\Shared\Contracts\Upload\UploadRecordReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class QueryTicketWorkflow
{
    public function __construct(
        private FileUploadGateway $imageUploadService,
        private UploadRecordReader $uploadRecordReader,
    ) {}

    /**
     * Get paginated query tickets for a campus with optional filters.
     */
    public function getTicketsForCampus(User $user, int $campusId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $userRoleIds = $user->campusUserRoles()
            ->where('campus_id', $campusId)
            ->pluck('role_id');

        $query = QueryTicket::query()
            ->with([
                'topic:id,title',
                'response' => function ($responseQuery) {
                    $responseQuery->with([
                        'form:id,title,type',
                        'campus:id,name',
                        'student:id,full_name,student_id',
                        'answers' => function ($answersQuery) {
                            $answersQuery
                                ->whereNotNull('answer_text')
                                ->orderBy('id');
                        },
                    ]);
                },
            ])
            ->whereHas('response', function ($responseQuery) use ($campusId) {
                $responseQuery->where('campus_id', $campusId);
            })
            ->whereHas('response.form', function ($formQuery) use ($userRoleIds) {
                $formQuery
                    ->where('type', 'query')
                    ->where(function ($visibilityQuery) use ($userRoleIds) {
                        $visibilityQuery
                            ->whereDoesntHave('resultVisibility')
                            ->orWhereHas('resultVisibility', function ($resultVisibilityQuery) use ($userRoleIds) {
                                $resultVisibilityQuery
                                    ->whereIn('role_id', $userRoleIds)
                                    ->where('visibility_level', 'full_detail');
                            });
                    });
            });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['question_id'])) {
            $query->whereHas('response.answers', function ($answersQuery) use ($filters) {
                $answersQuery->where('question_id', $filters['question_id']);
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get paginated query tickets for a specific student.
     */
    public function getTicketsForStudent(Student $student, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = QueryTicket::query()
            ->with([
                'topic:id,title',
                'response' => function ($responseQuery) {
                    $responseQuery->with([
                        'form:id,title,type',
                        'campus:id,name',
                        'student:id,full_name,student_id',
                        'answers' => function ($answersQuery) {
                            $answersQuery
                                ->select(['id', 'response_id', 'question_id', 'answer_text'])
                                ->orderBy('id');
                        },
                    ]);
                },
                'replies' => function ($repliesQuery) {
                    $repliesQuery
                        ->with(['author', 'authorStudent'])
                        ->orderByDesc('created_at');
                },
            ])
            ->whereHas('response', function ($responseQuery) use ($student) {
                $responseQuery->where('submitted_by_student_id', $student->id);
            });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $tickets = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $replyIds = $tickets->getCollection()
            ->flatMap(fn (QueryTicket $ticket) => $ticket->replies->pluck('id'))
            ->all();
        $replySummaries = $this->uploadRecordReader->byReplyIds($replyIds);

        foreach ($tickets->getCollection() as $ticket) {
            foreach ($ticket->replies as $reply) {
                $reply->setRelation('uploadRecord', $replySummaries[$reply->id] ?? null);
            }
        }

        return $tickets;
    }

    /**
     * Create a reply for the given ticket.
     */
    public function createReply(QueryTicket $ticket, User $author, array $data): QueryReply
    {
        return DB::transaction(function () use ($ticket, $author, $data) {
            $uploadId = $this->storeReplyUpload($ticket, $data['attachment'] ?? null, $author);

            /** @var QueryReply $reply */
            $reply = $ticket->replies()->create([
                'author_user_id' => $author->id,
                'message' => $data['message'],
                'is_official_answer' => (bool) ($data['is_official_answer'] ?? false),
                'upload_record_id' => $uploadId,
            ]);

            if ($uploadId) {
                $this->imageUploadService->linkToQueryReply($uploadId, (int) $ticket->id, (int) $reply->id);
            }

            if ($reply->is_official_answer) {
                $ticket->markAsAnswered();
            } elseif (! empty($data['set_pending'])) {
                $ticket->update(['status' => QueryTicket::STATUS_PENDING, 'closed_at' => null]);
            }

            $reply->load(['author', 'authorStudent']);
            $reply->setRelation(
                'uploadRecord',
                $this->uploadRecordReader->byReplyIds([$reply->id])[$reply->id] ?? null
            );

            return $reply;
        });
    }

    /**
     * Create a reply authored by a student.
     */
    public function createStudentReply(QueryTicket $ticket, Student $student, array $data): QueryReply
    {
        return DB::transaction(function () use ($ticket, $student, $data) {
            $uploadId = $this->storeReplyUpload($ticket, $data['attachment'] ?? null, null, $student);

            /** @var QueryReply $reply */
            $reply = $ticket->replies()->create([
                'author_student_id' => $student->id,
                'message' => $data['message'],
                'is_official_answer' => false,
                'upload_record_id' => $uploadId,
            ]);

            if ($uploadId) {
                $this->imageUploadService->linkToQueryReply($uploadId, (int) $ticket->id, (int) $reply->id);
            }

            if ($ticket->status !== QueryTicket::STATUS_CLOSED) {
                $ticket->update([
                    'status' => QueryTicket::STATUS_PENDING,
                    'closed_at' => null,
                ]);
            }

            $reply->load(['authorStudent']);
            $reply->setRelation(
                'uploadRecord',
                $this->uploadRecordReader->byReplyIds([$reply->id])[$reply->id] ?? null
            );

            return $reply;
        });
    }

    /**
     * Load a ticket ensuring it belongs to the student.
     */
    public function getStudentTicket(Student $student, QueryTicket $ticket): QueryTicket
    {
        if ($ticket->relationLoaded('response')) {
            $response = $ticket->response;
        } else {
            $response = $ticket->load('response')->response;
        }

        if (! $response || (int) $response->submitted_by_student_id !== (int) $student->id) {
            abort(404, 'Ticket not found');
        }

        return $ticket;
    }

    /**
     * Update the ticket status.
     */
    public function updateStatus(QueryTicket $ticket, string $status): QueryTicket
    {
        if (! in_array($status, QueryTicket::STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported status [{$status}]");
        }

        $updates = ['status' => $status];

        if ($status === QueryTicket::STATUS_CLOSED) {
            $updates['closed_at'] = now();
        } else {
            $updates['closed_at'] = null;
        }

        $ticket->update($updates);

        return $ticket->refresh()->load(['topic', 'response.form', 'response.campus', 'response.student']);
    }

    /**
     * Store upload record for a reply if a file is provided.
     */
    protected function storeReplyUpload(
        QueryTicket $ticket,
        ?UploadedFile $file,
        ?User $user = null,
        ?Student $student = null
    ): ?int {
        if (! $file) {
            return null;
        }

        $metadata = array_filter([
            'ticket_id' => $ticket->id,
            'response_id' => $ticket->response_id,
            'uploaded_by' => $user ? 'user' : 'student',
        ]);

        return $this->imageUploadService->store(
            $file,
            'form_attachment',
            $user?->id,
            $student?->id,
            $metadata
        )->id;
    }
}
