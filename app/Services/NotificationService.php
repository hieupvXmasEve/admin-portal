<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    protected EventNotificationService $eventNotificationService;

    public function __construct(EventNotificationService $eventNotificationService)
    {
        $this->eventNotificationService = $eventNotificationService;
    }

    /**
     * Notify all campus students about a new published event
     */
    public function notifyEventPublication(Event $event): void
    {
        $this->eventNotificationService->notifyEventPublication($event);
    }

    /**
     * Notify participants about event cancellation
     */
    public function notifyEventCancellation(Event $event, ?string $reason = null): void
    {
        $this->eventNotificationService->notifyEventCancellation($event, $reason);
    }

    /**
     * Notify a student about event updates
     */
    public function notifyEventUpdate(Event $event, Student $student): void
    {
        $this->eventNotificationService->notifyEventUpdate($event, $student);
    }

    /**
     * Send reminder notifications for upcoming events
     */
    public function sendEventReminders(): int
    {
        return $this->eventNotificationService->sendEventReminders();
    }

    /**
     * Notify about event completion
     */
    public function notifyEventCompletion(Event $event): void
    {
        $this->eventNotificationService->notifyEventCompletion($event);
    }

    /**
     * Send event registration confirmation to student
     */
    public function sendEventRegistrationConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationService->sendEventRegistrationConfirmation($student, $event);
    }

    /**
     * Send event cancellation confirmation to student
     */
    public function sendEventCancellationConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationService->sendEventCancellationConfirmation($student, $event);
    }

    /**
     * Send event check-in confirmation to student
     */
    public function sendEventCheckinConfirmation(Student $student, Event $event): void
    {
        $this->eventNotificationService->sendEventCheckinConfirmation($student, $event);
    }

    /**
     * Send gold reward notification to student
     */
    public function sendGoldRewardNotification(Student $student, float $amount, Event $event): void
    {
        $this->eventNotificationService->sendGoldRewardNotification($student, $amount, $event);
    }

    /**
     * Send gold reclaim notification to student
     */
    public function sendGoldReclaimNotification(Student $student, float $amount, Event $event): void
    {
        $this->eventNotificationService->sendGoldReclaimNotification($student, $amount, $event);
    }
}
