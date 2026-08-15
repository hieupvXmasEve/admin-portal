<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Support;

use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\QueryReply;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Identity\DTO\StaffActorReference;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReplyToQueryAction
{
    public function __construct(
        private DomainEventPublisher $domainEventPublisher,
        private NotificationPayloadFactory $notificationPayloadFactory,
        private FileUploadGateway $imageUploadService,
    ) {}

    public function execute(
        FormResponse $response,
        StaffActorReference $author,
        string $message,
        bool $isOfficial = true,
        ?int $uploadRecordId = null,
        ?bool $setPending = false
    ): QueryReply {
        return DB::transaction(function () use ($response, $author, $message, $isOfficial, $uploadRecordId, $setPending) {
            $ticket = $response->queryTicket;

            if (! $ticket) {
                $ticket = $response->queryTicket()->create([
                    'status' => 'open',
                    'priority' => 'normal',
                ]);
            }

            $reply = QueryReply::create([
                'ticket_id' => $ticket->id,
                'author_user_id' => $author->id,
                'message' => $message,
                'is_official_answer' => $isOfficial,
                'upload_record_id' => $uploadRecordId,
            ]);

            if ($uploadRecordId !== null) {
                $this->imageUploadService->linkToQueryReply($uploadRecordId, (int) $ticket->id, (int) $reply->id);
            }

            $newStatus = $isOfficial ? 'answered' : ($setPending ? 'pending' : 'open');

            $ticket->update(['status' => $newStatus]);
            $response->update(['query_status' => $newStatus]);

            $this->dispatchStaffReplyNotification($response, $ticket, $reply, $author);

            return $reply;
        });
    }

    private function dispatchStaffReplyNotification(FormResponse $response, $ticket, QueryReply $reply, StaffActorReference $author): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        $response->load(['student', 'form']);
        $ticket->load(['topic']);

        $student = $response->student;
        if (! $student) {
            return;
        }

        $recipientTargets = [
            ['type' => 'student', 'id' => $student->id],
        ];

        $topicTitle = $ticket->topic?->title ?? $ticket->custom_topic_text ?? 'General';

        $payload = $this->notificationPayloadFactory->build('query_staff_reply', [
            'title' => 'Reply to Your Query: '.$topicTitle,
            'body' => $author->name.' replied: "'.Str::limit($reply->message, 80).'"',
            'action_type' => 'query.student_detail',
            'action_params' => ['id' => $ticket->id],
        ]);

        $event = new DomainEvent(
            name: 'query.staff_reply_created',
            deduplicationKey: 'query.staff_reply_created:'.Str::uuid(),
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'query_reply',
            aggregateId: (string) $reply->id,
            campusId: $response->campus_id,
            actorUserId: $author->id,
            payload: [
                'type_key' => 'query_staff_reply',
                'recipient_targets' => $recipientTargets,
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }
}
