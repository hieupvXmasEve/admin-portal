<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Campus;
use App\Models\QueryReply;
use App\Models\QueryTicket;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class QueryTicketService
{
    /**
     * Get paginated query tickets for a campus with optional filters.
     */
    public function getTicketsForCampus(User $user, Campus $campus, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $userRoleIds = $user->campusUserRoles()
            ->where('campus_id', $campus->id)
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
            ->whereHas('response', function ($responseQuery) use ($campus) {
                $responseQuery->where('campus_id', $campus->id);
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

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['topic_id'])) {
            if ($filters['topic_id'] === 'custom') {
                $query->whereNull('topic_id')->whereNotNull('custom_topic_text');
            } else {
                $query->where('topic_id', $filters['topic_id']);
            }
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
                        ->with(['author', 'authorStudent', 'attachment'])
                        ->orderByDesc('created_at');
                },
            ])
            ->whereHas('response', function ($responseQuery) use ($student) {
                $responseQuery->where('submitted_by_student_id', $student->id);
            });

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a reply for the given ticket.
     */
    public function createReply(QueryTicket $ticket, User $author, array $data): QueryReply
    {
        return DB::transaction(function () use ($ticket, $author, $data) {
            $attachment = $this->storeAttachment($ticket, $data['attachment'] ?? null);

            /** @var QueryReply $reply */
            $reply = $ticket->replies()->create([
                'author_user_id' => $author->id,
                'message' => $data['message'],
                'is_official_answer' => (bool) ($data['is_official_answer'] ?? false),
                'attachment_id' => $attachment?->id,
            ]);

            if ($reply->is_official_answer) {
                $ticket->markAsAnswered();
            } elseif (!empty($data['set_pending'])) {
                $ticket->update(['status' => QueryTicket::STATUS_PENDING, 'closed_at' => null]);
            }

            return $reply->load(['author', 'authorStudent', 'attachment']);
        });
    }

    /**
     * Create a reply authored by a student.
     */
    public function createStudentReply(QueryTicket $ticket, Student $student, array $data): QueryReply
    {
        return DB::transaction(function () use ($ticket, $student, $data) {
            $attachment = $this->storeAttachment($ticket, $data['attachment'] ?? null);

            /** @var QueryReply $reply */
            $reply = $ticket->replies()->create([
                'author_student_id' => $student->id,
                'message' => $data['message'],
                'is_official_answer' => false,
                'attachment_id' => $attachment?->id,
            ]);

            if ($ticket->status !== QueryTicket::STATUS_CLOSED) {
                $ticket->update([
                    'status' => QueryTicket::STATUS_PENDING,
                    'closed_at' => null,
                ]);
            }

            return $reply->load(['authorStudent', 'attachment']);
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

        if (!$response || (int) $response->submitted_by_student_id !== (int) $student->id) {
            abort(404, 'Ticket not found');
        }

        return $ticket;
    }

    /**
     * Update the ticket status.
     */
    public function updateStatus(QueryTicket $ticket, string $status): QueryTicket
    {
        if (!in_array($status, QueryTicket::STATUSES, true)) {
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
     * Store attachment for a reply if provided.
     */
    protected function storeAttachment(QueryTicket $ticket, ?UploadedFile $file): ?Attachment
    {
        if (!$file) {
            return null;
        }

        $path = $file->store('query-replies/' . date('Y/m'), 'private');

        return Attachment::create([
            'response_id' => $ticket->response_id,
            'storage_key' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
    }
}
