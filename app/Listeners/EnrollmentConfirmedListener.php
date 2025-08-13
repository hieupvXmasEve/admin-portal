<?php

namespace App\Listeners;

use App\Events\EnrollmentConfirmed;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class EnrollmentConfirmedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(EnrollmentConfirmed $event): void
    {
        try {
            Log::info('Processing enrollment confirmed event', [
                'student_id' => $event->student->id,
                'enrollment_id' => $event->enrollment->id,
                'course_offering_id' => $event->courseOffering->id,
            ]);

            // Send welcome/confirmation notification to student
            $result = $this->notificationService->processEventNotification(
                'enrollment_confirmed',
                $event->getNotificationData()
            );

            Log::info('Enrollment confirmation notification sent', [
                'result' => $result,
                'student_id' => $event->student->id,
                'enrollment_id' => $event->enrollment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process enrollment confirmation notification', [
                'student_id' => $event->student->id,
                'enrollment_id' => $event->enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle bulk enrollment confirmations
     */
    public function handleBulk(\Illuminate\Support\Collection $events): void
    {
        try {
            Log::info('Processing bulk enrollment confirmed events', [
                'events_count' => $events->count(),
            ]);

            // Group events by course offering for efficient processing
            $eventsByCourse = $events->groupBy(fn($event) => $event->courseOffering->id);

            foreach ($eventsByCourse as $courseOfferingId => $courseEvents) {
                $result = $this->notificationService->processEventNotification(
                    'enrollment_confirmed',
                    EnrollmentConfirmed::getBulkNotificationData($courseEvents)
                );

                Log::info('Bulk enrollment confirmation notification sent', [
                    'result' => $result,
                    'course_offering_id' => $courseOfferingId,
                    'students_count' => $courseEvents->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process bulk enrollment confirmation notifications', [
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
    public function failed(EnrollmentConfirmed $event, \Throwable $exception): void
    {
        Log::error('Enrollment confirmed listener job failed permanently', [
            'student_id' => $event->student->id,
            'enrollment_id' => $event->enrollment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
