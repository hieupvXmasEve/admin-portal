<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

/**
 * Compatibility facade for Event callers.
 *
 * Event notifications are now published as domain facts and materialized by
 * Notification V2. This class deliberately does not write legacy
 * `notifications` rows or deliver email directly.
 */
final class EventNotificationPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly StudentReferenceReader $studentReferenceReader,
        private readonly StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {}

    public function notifyEventPublication(Event $event): void
    {
        $studentIds = $this->activeStudentIdsForCampus((int) $event->campus_id);

        $this->publish(
            eventName: 'event.published',
            deduplicationKey: "event:{$event->id}:published",
            event: $event,
            studentIds: $studentIds,
            typeKey: 'event_publication',
            title: 'New Event Published',
            body: "A new event '{$event->title}' has been published for your campus.",
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'start_time' => $event->start_time?->toISOString(),
                'location' => $event->location,
                'gold_reward' => $event->gold_reward_amount,
                'action_url' => "/events/{$event->id}",
                'action_text' => 'View Event',
                'category' => 'event',
            ],
        );
    }

    public function sendEventRegistrationConfirmation(int $studentId, Event $event): void
    {
        $this->publish(
            eventName: 'event.registration_confirmed',
            deduplicationKey: "event:{$event->id}:student:{$studentId}:registration_confirmed",
            event: $event,
            studentIds: [$studentId],
            typeKey: 'event_registration',
            title: 'Event Registration Confirmed',
            body: "You have successfully registered for '{$event->title}'. Don't forget to check in when the event starts!",
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'start_time' => $event->start_time?->toISOString(),
                'end_time' => $event->end_time?->toISOString(),
                'location' => $event->location,
                'gold_reward' => $event->gold_reward_amount,
                'qr_code' => $event->qr_code,
                'action_url' => "/my-events/{$event->id}",
                'action_text' => 'View Registration',
                'category' => 'event',
            ],
        );
    }

    public function sendEventCheckinConfirmation(int $studentId, Event $event): void
    {
        $goldMessage = (float) $event->gold_reward_amount > 0
            ? " You will receive {$event->gold_reward_amount} gold when the event ends."
            : '';

        $this->publish(
            eventName: 'event.checkin_confirmed',
            deduplicationKey: "event:{$event->id}:student:{$studentId}:checkin_confirmed",
            event: $event,
            studentIds: [$studentId],
            typeKey: 'event_checkin',
            title: 'Event Check-in Confirmed',
            body: "You have successfully checked in to '{$event->title}'.{$goldMessage}",
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'checkin_time' => now()->toISOString(),
                'end_time' => $event->end_time?->toISOString(),
                'gold_reward' => $event->gold_reward_amount,
                'action_url' => "/my-events/{$event->id}",
                'action_text' => 'View Event',
                'category' => 'event',
            ],
        );
    }

    public function sendGoldRewardNotification(int $studentId, float $amount, Event $event): void
    {
        $this->publishGoldNotification($studentId, $amount, $event, false);
    }

    public function sendGoldReclaimNotification(int $studentId, float $amount, Event $event): void
    {
        $this->publishGoldNotification($studentId, $amount, $event, true);
    }

    public function notifyEventCancellation(Event $event, ?string $reason = null): void
    {
        $event->participants()
            ->whereIn('status', ['registered', 'checked_in', 'cancelled'])
            ->with('student')
            ->each(function (EventParticipant $participant) use ($event, $reason): void {
                if (! $participant->student) {
                    return;
                }

                $goldRefund = $participant->hasBeenAwarded();
                $reasonText = $reason !== null && $reason !== '' ? " Reason: {$reason}" : '';
                $goldRefundText = $goldRefund ? ' Any gold rewards will be reclaimed from your wallet.' : '';

                $this->publish(
                    eventName: 'event.cancelled',
                    deduplicationKey: "event:{$event->id}:participant:{$participant->id}:cancelled",
                    event: $event,
                    studentIds: [(int) $participant->student_id],
                    typeKey: 'event_cancellation',
                    title: 'Event Cancelled',
                    body: "The event '{$event->title}' has been cancelled.{$reasonText}{$goldRefundText}",
                    data: [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'cancellation_reason' => $reason,
                        'cancelled_at' => $event->cancelled_at?->toISOString(),
                        'gold_refund' => $goldRefund,
                        'action_url' => '/my-events',
                        'action_text' => 'View My Events',
                        'category' => 'event',
                        'is_important' => true,
                    ],
                );
            });
    }

    public function sendEventCancellationConfirmation(int $studentId, Event $event): void
    {
        $this->publish(
            eventName: 'event.registration_cancelled',
            deduplicationKey: "event:{$event->id}:student:{$studentId}:registration_cancelled",
            event: $event,
            studentIds: [$studentId],
            typeKey: 'event_registration',
            title: 'Event Registration Cancelled',
            body: "Your registration for '{$event->title}' has been cancelled successfully.",
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'cancelled_at' => now()->toISOString(),
                'action_url' => '/events',
                'action_text' => 'Browse Events',
                'category' => 'event',
            ],
        );
    }

    public function notifyEventUpdate(Event $event, int $studentId): void
    {
        $this->publish(
            eventName: 'event.updated',
            deduplicationKey: "event:{$event->id}:student:{$studentId}:updated:{$event->updated_at?->getTimestamp()}",
            event: $event,
            studentIds: [$studentId],
            typeKey: 'event_update',
            title: 'Event Updated',
            body: "The event '{$event->title}' has been updated. Please check the latest details.",
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'updated_at' => $event->updated_at?->toISOString(),
                'start_time' => $event->start_time?->toISOString(),
                'end_time' => $event->end_time?->toISOString(),
                'location' => $event->location,
                'action_url' => "/events/{$event->id}",
                'action_text' => 'View Updated Event',
                'category' => 'event',
            ],
        );
    }

    public function sendEventReminders(): int
    {
        $queued = 0;

        Event::query()
            ->published()
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addDay())
            ->with(['participants.student'])
            ->each(function (Event $event) use (&$queued): void {
                foreach ($event->participants as $participant) {
                    if (! $participant->isRegistered() || ! $participant->student) {
                        continue;
                    }

                    $hoursUntilStart = CarbonInterval::minutes(now()->diffInMinutes($event->start_time))->forHumans([
                        'short' => true,
                        'parts' => 1,
                    ]);

                    $this->publish(
                        eventName: 'event.reminder_requested',
                        deduplicationKey: sprintf('event:%d:participant:%d:reminder:%s', $event->id, $participant->id, now()->toDateString()),
                        event: $event,
                        studentIds: [(int) $participant->student_id],
                        typeKey: 'reminder',
                        title: 'Event Reminder',
                        body: "Don't forget! '{$event->title}' starts in {$hoursUntilStart} at {$event->location}.",
                        data: [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'start_time' => $event->start_time?->toISOString(),
                            'location' => $event->location,
                            'hours_until_start' => $hoursUntilStart,
                            'action_url' => "/events/{$event->id}",
                            'action_text' => 'View Event',
                            'category' => 'event',
                        ],
                    );
                    $queued++;
                }
            });

        return $queued;
    }

    public function notifyEventCompletion(Event $event): void
    {
        $event->participants()
            ->where('status', 'completed')
            ->with('student')
            ->each(function (EventParticipant $participant) use ($event): void {
                if (! $participant->student) {
                    return;
                }

                $goldEarned = $participant->hasBeenAwarded() ? (float) $event->gold_reward_amount : 0.0;
                $goldText = $goldEarned > 0 ? " You've earned {$event->gold_reward_amount} gold for your participation!" : '';

                $this->publish(
                    eventName: 'event.completed',
                    deduplicationKey: "event:{$event->id}:participant:{$participant->id}:completed",
                    event: $event,
                    studentIds: [(int) $participant->student_id],
                    typeKey: 'event_registration',
                    title: 'Event Completed',
                    body: "Thank you for attending '{$event->title}'!{$goldText}",
                    data: [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'completed_at' => $event->completed_at?->toISOString(),
                        'gold_earned' => $goldEarned,
                        'action_url' => "/my-events/{$event->id}",
                        'action_text' => 'View Event',
                        'category' => 'event',
                    ],
                );
            });
    }

    private function publishGoldNotification(int $studentId, float $amount, Event $event, bool $isReclaim): void
    {
        $verb = $isReclaim ? 'reclaimed' : 'earned';
        $title = $isReclaim ? 'Gold Reclaimed' : 'Gold Reward Earned!';
        $body = $isReclaim
            ? "Due to your cancellation from '{$event->title}', {$amount} gold has been reclaimed from your wallet."
            : "Congratulations! You've earned {$amount} gold for attending '{$event->title}'. Keep participating in events to earn more rewards!";

        $this->publish(
            eventName: "event.gold_{$verb}",
            deduplicationKey: "event:{$event->id}:student:{$studentId}:gold:{$verb}:{$amount}",
            event: $event,
            studentIds: [$studentId],
            typeKey: 'event_gold_reward',
            title: $title,
            body: $body,
            data: [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'gold_amount' => $amount,
                'reward_time' => now()->toISOString(),
                'action_url' => '/wallet',
                'action_text' => 'View Wallet',
                'category' => 'event',
                'is_important' => ! $isReclaim,
            ],
        );
    }

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<string, mixed>  $data
     */
    private function publish(
        string $eventName,
        string $deduplicationKey,
        Event $event,
        array $studentIds,
        string $typeKey,
        string $title,
        string $body,
        array $data,
    ): void {
        if ($studentIds === []) {
            return;
        }

        $this->domainEventPublisher->publishAfterCommit(new DomainEvent(
            name: $eventName,
            deduplicationKey: $deduplicationKey,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'event',
            aggregateId: (string) $event->id,
            campusId: (int) $event->campus_id,
            actorUserId: $event->created_by_user_id,
            payload: [
                'type_key' => $typeKey,
                'channels' => ['realtime'],
                'recipient_targets' => array_map(
                    static fn (int $studentId): array => ['type' => 'student', 'id' => $studentId],
                    array_values(array_unique($studentIds)),
                ),
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    ...$data,
                ],
            ],
        ));
    }

    /** @return list<int> */
    private function activeStudentIdsForCampus(int $campusId): array
    {
        $studentIds = $this->studentReferenceReader->idsForCampus($campusId);
        $blockedStatuses = ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'];
        $statuses = $this->studentLifecycleStatusReader->statusesFor($studentIds);

        return array_values(array_filter(
            $studentIds,
            fn (int $studentId): bool => ! in_array(
                $statuses[$studentId] ?? null,
                $blockedStatuses,
                true,
            ),
        ));
    }
}
