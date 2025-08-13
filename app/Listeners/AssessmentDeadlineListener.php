<?php

namespace App\Listeners;

use App\Events\AssessmentDeadlineApproaching;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AssessmentDeadlineListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(AssessmentDeadlineApproaching $event): void
    {
        try {
            Log::info('Processing assessment deadline approaching event', [
                'assessment_id' => $event->assessmentDetail->id,
                'course_offering_id' => $event->courseOffering->id,
                'recipients_count' => $event->recipients->count(),
                'days_until_deadline' => $event->daysUntilDeadline,
                'is_critical' => $event->isCriticalDeadline(),
            ]);

            // Send deadline reminder notification
            $result = $this->notificationService->processEventNotification(
                'assessment_deadline_approaching',
                $event->getNotificationData()
            );

            Log::info('Assessment deadline notification sent', [
                'result' => $result,
                'assessment_id' => $event->assessmentDetail->id,
                'course_offering_id' => $event->courseOffering->id,
                'recipients_count' => $event->recipients->count(),
            ]);

            // If it's a critical deadline (1 day or less), also schedule a final reminder
            if ($event->isCriticalDeadline() && $event->daysUntilDeadline > 0) {
                $this->scheduleFinalReminder($event);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process assessment deadline notification', [
                'assessment_id' => $event->assessmentDetail->id,
                'course_offering_id' => $event->courseOffering->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Schedule a final reminder for critical deadlines
     */
    protected function scheduleFinalReminder(AssessmentDeadlineApproaching $event): void
    {
        try {
            // Schedule reminder for 2 hours before deadline
            $reminderTime = $event->assessmentDetail->due_date?->subHours(2) ?? now()->addHours(22);

            // Only schedule if the reminder time is in the future
            if ($reminderTime->isFuture()) {
                $this->notificationService->scheduleReminder(
                    'assessment_final_reminder',
                    $event->recipients,
                    $reminderTime,
                    array_merge($event->getNotificationData(), [
                        'is_final_reminder' => true,
                        'hours_until_deadline' => 2,
                    ])
                );

                Log::info('Final assessment reminder scheduled', [
                    'assessment_id' => $event->assessmentDetail->id,
                    'reminder_time' => $reminderTime->format('Y-m-d H:i:s'),
                    'recipients_count' => $event->recipients->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to schedule final assessment reminder', [
                'assessment_id' => $event->assessmentDetail->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(AssessmentDeadlineApproaching $event, \Throwable $exception): void
    {
        Log::error('Assessment deadline listener job failed permanently', [
            'assessment_id' => $event->assessmentDetail->id,
            'course_offering_id' => $event->courseOffering->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
