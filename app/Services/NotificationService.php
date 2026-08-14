<?php

namespace App\Services;

use App\Models\Student;
use App\Modules\Engagement\Actions\EventNotificationPublisher;
use App\Modules\Engagement\Models\Event;

class NotificationService
{
    protected EventNotificationPublisher $eventNotificationPublisher;

    public function __construct(EventNotificationPublisher $eventNotificationPublisher)
    {
        $this->eventNotificationPublisher = $eventNotificationPublisher;
    }

    /**
     * Notify all campus students about a new published event
     */
    public function notifyEventPublication(Event $event): void
    {
        $this->eventNotificationPublisher->notifyEventPublication($event);
    }

    /**
     * Notify participants about event cancellation
     */
    public function notifyEventCancellation(Event $event, ?string $reason = null): void
    {
        $this->eventNotificationPublisher->notifyEventCancellation($event, $reason);
    }

    /**
     * Notify a student about event updates
     */
    public function notifyEventUpdate(Event $event, Student $student): void
    {
        $this->eventNotificationPublisher->notifyEventUpdate($event, (int) $student->id);
    }

    /**
     * Send reminder notifications for upcoming events
     */
    public function sendEventReminders(): int
    {
        return $this->eventNotificationPublisher->sendEventReminders();
    }

    /**
     * Notify about event completion
     */
    public function notifyEventCompletion(Event $event): void
    {
        $this->eventNotificationPublisher->notifyEventCompletion($event);
    }

    /**
     * Send event registration confirmation to student
     */
    public function sendEventRegistrationConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationPublisher->sendEventRegistrationConfirmation((int) $student->id, $event);
    }

    /**
     * Send event cancellation confirmation to student
     */
    public function sendEventCancellationConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationPublisher->sendEventCancellationConfirmation((int) $student->id, $event);
    }

    /**
     * Send event check-in confirmation to student
     */
    public function sendEventCheckinConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationPublisher->sendEventCheckinConfirmation((int) $student->id, $event);
    }

    /**
     * Send gold reward notification to student
     */
    public function sendGoldRewardNotification(Student $student, float $amount, Event $event): void
    {
        $this->eventNotificationPublisher->sendGoldRewardNotification((int) $student->id, $amount, $event);
    }

    /**
     * Send gold reclaim notification to student
     */
    public function sendGoldReclaimNotification(Student $student, float $amount, Event $event): void
    {
        $this->eventNotificationPublisher->sendGoldReclaimNotification((int) $student->id, $amount, $event);
    }
}
