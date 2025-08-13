<?php

namespace App\Listeners;

use App\Events\CourseRegistrationOpened;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CourseRegistrationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(CourseRegistrationOpened $event): void
    {
        try {
            Log::info('Processing course registration opened event', [
                'course_offering_id' => $event->courseOffering->id,
                'semester_id' => $event->semester->id,
                'lecturers_count' => count($event->lecturers),
            ]);

            // Send notification to lecturers about course registration opening
            $result = $this->notificationService->processEventNotification(
                'course_registration_opened',
                $event->getNotificationData()
            );

            Log::info('Course registration notification sent', [
                'result' => $result,
                'course_offering_id' => $event->courseOffering->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process course registration notification', [
                'course_offering_id' => $event->courseOffering->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(CourseRegistrationOpened $event, \Throwable $exception): void
    {
        Log::error('Course registration listener job failed permanently', [
            'course_offering_id' => $event->courseOffering->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
