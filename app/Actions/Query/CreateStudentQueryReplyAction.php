<?php

declare(strict_types=1);

namespace App\Actions\Query;

use App\Models\Department;
use App\Models\QueryReply;
use App\Models\QueryTicket;
use App\Models\Student;
use App\Models\UploadRecord;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Support\NotificationPayloadBuilder;
use App\Services\ImageUploadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateStudentQueryReplyAction
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private PublishDomainEventAction $publishDomainEventAction,
        private NotificationPayloadBuilder $payloadBuilder
    ) {}

    public function execute(QueryTicket $ticket, Student $student, array $data): QueryReply
    {
        if ($ticket->status === QueryTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages(['ticket' => 'This query has been closed and cannot be updated.']);
        }

        return DB::transaction(function () use ($ticket, $student, $data) {
            $uploadRecord = $this->storeReplyUpload($ticket, $data['attachment'] ?? null, $student);

            /** @var QueryReply $reply */
            $reply = $ticket->replies()->create([
                'author_student_id' => $student->id,
                'message' => $data['message'],
                'is_official_answer' => false,
                'upload_record_id' => $uploadRecord?->id,
            ]);

            if ($uploadRecord) {
                $metadata = $uploadRecord->metadata ?? [];
                $metadata['reply_id'] = $reply->id;
                $metadata['ticket_id'] = $ticket->id;

                $uploadRecord->update([
                    'reply_id' => $reply->id,
                    'ticket_id' => $ticket->id,
                    'metadata' => $metadata,
                ]);
            }

            if ($ticket->status !== QueryTicket::STATUS_CLOSED) {
                $ticket->update([
                    'status' => QueryTicket::STATUS_PENDING,
                    'closed_at' => null,
                ]);
            }

            $reply->load(['authorStudent', 'uploadRecord']);

            $this->dispatchReplyCreatedNotification($ticket, $reply, $student);

            return $reply;
        });
    }

    private function storeReplyUpload(QueryTicket $ticket, UploadedFile|null $file, Student $student): ?UploadRecord
    {
        if (! $file) {
            return null;
        }

        $metadata = [
            'ticket_id' => $ticket->id,
            'response_id' => $ticket->response_id,
            'uploaded_by' => 'student',
        ];

        return $this->imageUploadService->upload(
            $file,
            'form_attachment',
            null,
            $student->id,
            $metadata
        );
    }

    private function dispatchReplyCreatedNotification(QueryTicket $ticket, QueryReply $reply, Student $student): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        $ticket->load(['response.formTarget', 'response.form', 'topic']);

        $formTarget = $ticket->response?->formTarget;
        if (! $formTarget || $formTarget->scope_type !== 'department') {
            return;
        }

        $departmentId = (int) $formTarget->scope_id;
        $department = Department::find($departmentId);
        if (! $department) {
            return;
        }

        $userIds = $department->members()->pluck('user_id')->unique()->toArray();
        if (empty($userIds)) {
            return;
        }

        $recipientTargets = array_map(
            fn(int $id) => ['type' => 'user', 'id' => $id],
            $userIds
        );

        $topicTitle = $ticket->topic?->title ?? $ticket->custom_topic_text ?? 'General';

        $payload = $this->payloadBuilder->build('query_reply_created', [
            'title' => 'New Reply: ' . $topicTitle,
            'body' => $student->full_name . ' replied: "' . Str::limit($reply->message, 80) . '"',
            'action_type' => 'query.admin_inbox',
            'action_params' => ['id' => $ticket->response_id],
        ]);

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'query.reply_created',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'query_reply',
            aggregateId: (string) $reply->id,
            campusId: $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'query_reply_created',
                'recipient_targets' => $recipientTargets,
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }
}
