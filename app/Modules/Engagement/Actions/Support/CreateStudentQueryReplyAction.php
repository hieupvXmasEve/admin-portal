<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Support;

use App\Models\QueryReply;
use App\Models\QueryTicket;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateStudentQueryReplyAction
{
    public function __construct(
        private FileUploadGateway $imageUploadService,
        private DomainEventPublisher $domainEventPublisher,
        private NotificationPayloadFactory $notificationPayloadFactory,
        private DepartmentReferenceReader $departments,
    ) {}

    public function execute(QueryTicket $ticket, StudentReference $student, array $data): QueryReply
    {
        if ($ticket->status === QueryTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages(['ticket' => 'This query has been closed and cannot be updated.']);
        }

        return DB::transaction(function () use ($ticket, $student, $data) {
            $uploadId = $this->storeReplyUpload($ticket, $data['attachment'] ?? null, $student);

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

            $reply->load(['authorStudent', 'uploadRecord']);

            $this->dispatchReplyCreatedNotification($ticket, $reply, $student);

            return $reply;
        });
    }

    private function storeReplyUpload(QueryTicket $ticket, ?UploadedFile $file, StudentReference $student): ?int
    {
        if (! $file) {
            return null;
        }

        $metadata = [
            'ticket_id' => $ticket->id,
            'response_id' => $ticket->response_id,
            'uploaded_by' => 'student',
        ];

        return $this->imageUploadService->store(
            $file,
            'form_attachment',
            null,
            $student->id,
            $metadata
        )->id;
    }

    private function dispatchReplyCreatedNotification(QueryTicket $ticket, QueryReply $reply, StudentReference $student): void
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
        $userIds = $this->departments->activeMemberUserIds($departmentId);
        if (empty($userIds)) {
            return;
        }

        $recipientTargets = array_map(
            fn (int $id) => ['type' => 'user', 'id' => $id],
            $userIds
        );

        $topicTitle = $ticket->topic?->title ?? $ticket->custom_topic_text ?? 'General';

        $payload = $this->notificationPayloadFactory->build('query_reply_created', [
            'title' => 'New Reply: '.$topicTitle,
            'body' => $student->fullName.' replied: "'.Str::limit($reply->message, 80).'"',
            'action_type' => 'query.admin_inbox',
            'action_params' => ['id' => $ticket->response_id],
        ]);

        $event = new DomainEvent(
            name: 'query.reply_created',
            deduplicationKey: 'query.reply_created:'.Str::uuid(),
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'query_reply',
            aggregateId: (string) $reply->id,
            campusId: $student->campusId,
            actorUserId: null,
            payload: [
                'type_key' => 'query_reply_created',
                'recipient_targets' => $recipientTargets,
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }
}
