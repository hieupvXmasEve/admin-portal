<?php

namespace App\Listeners;

use App\Events\GradePublished;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class GradePublishedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(GradePublished $event): void
    {
        try {
            Log::info('Processing grade published event', [
                'student_id' => $event->student->id,
                'course_offering_id' => $event->courseOffering->id,
                'assessment_score_id' => $event->gradeScore->id,
            ]);

            // Send notification to student about published grade
            $result = $this->notificationService->processEventNotification(
                'grades_published',
                $event->getNotificationData()
            );

            Log::info('Grade notification sent', [
                'result' => $result,
                'student_id' => $event->student->id,
                'course_offering_id' => $event->courseOffering->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process grade published notification', [
                'student_id' => $event->student->id,
                'course_offering_id' => $event->courseOffering->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle bulk grade publishing
     */
    public function handleBulk(\Illuminate\Support\Collection $events): void
    {
        try {
            Log::info('Processing bulk grade published events', [
                'events_count' => $events->count(),
            ]);

            // Group events by course offering for efficient processing
            $eventsByCourse = $events->groupBy(fn($event) => $event->courseOffering->id);

            foreach ($eventsByCourse as $courseOfferingId => $courseEvents) {
                $result = $this->notificationService->processEventNotification(
                    'grades_published',
                    GradePublished::getBulkNotificationData($courseEvents)
                );

                Log::info('Bulk grade notification sent', [
                    'result' => $result,
                    'course_offering_id' => $courseOfferingId,
                    'students_count' => $courseEvents->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process bulk grade published notifications', [
                'events_count' => $events->count(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(GradePublished $event, \Throwable $exception): void
    {
        Log::error('Grade published listener job failed permanently', [
            'student_id' => $event->student->id,
            'course_offering_id' => $event->courseOffering->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
