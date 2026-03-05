<?php

declare(strict_types=1);

namespace App\Actions\Query;

use App\Models\FormResponse;
use App\Models\QueryAssignment;
use App\Models\User;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Support\NotificationPayloadBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssignQueryAction
{
    public function __construct(
        private PublishDomainEventAction $publishDomainEventAction,
        private NotificationPayloadBuilder $payloadBuilder
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(FormResponse $response, ?int $toAssigneeUserId, User $actor, ?string $note = null): FormResponse
    {
        $formTarget = $response->formTarget;
        if (! $formTarget || $formTarget->scope_type !== 'department') {
            throw ValidationException::withMessages([
                'response' => 'Only department-scoped queries can be assigned through this workflow.',
            ]);
        }

        $departmentId = (int) $formTarget->scope_id;

        if (! $actor->isDepartmentHead($departmentId) && ! $actor->hasSystemRole('admin')) {
            throw ValidationException::withMessages([
                'auth' => 'Only department heads or admins can assign tickets.',
            ]);
        }

        $toAssignee = null;
        if ($toAssigneeUserId) {
            $toAssignee = User::find($toAssigneeUserId);
            if (! $toAssignee || ! $toAssignee->isDepartmentMember($departmentId)) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'The assignee must be an active member of the department.',
                ]);
            }
        }

        $fromAssigneeUserId = $response->assigned_to_user_id;

        if ($fromAssigneeUserId && $toAssigneeUserId) {
            $action = 'reassign';
        } elseif ($toAssigneeUserId) {
            $action = 'assign';
        } else {
            $action = 'unassign';
        }

        return DB::transaction(function () use ($response, $toAssigneeUserId, $toAssignee, $actor, $fromAssigneeUserId, $action, $note, $departmentId, $formTarget) {
            $response->update([
                'assigned_to_user_id' => $toAssigneeUserId,
                'query_status' => $toAssigneeUserId ? 'pending' : $response->query_status,
            ]);

            if ($response->queryTicket) {
                $response->queryTicket->update([
                    'assigned_to_user_id' => $toAssigneeUserId,
                    'status' => $toAssigneeUserId ? 'pending' : $response->queryTicket->status,
                ]);
            }

            QueryAssignment::create([
                'query_ticket_id' => $response->queryTicket->id,
                'response_id' => $response->id,
                'department_id' => $departmentId,
                'form_target_id' => $formTarget->id,
                'from_assignee_user_id' => $fromAssigneeUserId,
                'to_assignee_user_id' => $toAssigneeUserId,
                'assigned_by_user_id' => $actor->id,
                'action' => $action,
                'note' => $note,
            ]);

            if ($toAssignee && $action !== 'unassign') {
                $this->dispatchAssignNotification($response, $toAssignee, $actor, $action);
            }

            return $response->fresh(['assignedTo', 'assignments']);
        });
    }

    private function dispatchAssignNotification(FormResponse $response, User $assignee, User $actor, string $action): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        $response->load(['queryTicket.topic', 'form', 'student']);

        $ticket = $response->queryTicket;
        $topicTitle = $ticket?->topic?->title ?? $ticket?->custom_topic_text ?? 'General';
        $studentName = $response->student?->full_name ?? 'Unknown';

        $recipientTargets = [
            ['type' => 'user', 'id' => $assignee->id],
        ];

        $title = $action === 'reassign'
            ? 'Query Reassigned: ' . $topicTitle
            : 'Query Assigned: ' . $topicTitle;

        $body = $actor->name . ' assigned you a query from ' . $studentName;

        $payload = $this->payloadBuilder->build('query_assigned', [
            'title' => $title,
            'body' => $body,
            'action_type' => 'query.admin_inbox',
            'action_params' => ['id' => $response->id],
        ]);

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'query.assigned',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'query_assignment',
            aggregateId: (string) $response->id,
            campusId: $response->campus_id,
            actorUserId: $actor->id,
            payload: [
                'type_key' => 'query_assigned',
                'recipient_targets' => $recipientTargets,
                'channels' => ['email', 'realtime'],
                'data' => $payload,
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }
}
