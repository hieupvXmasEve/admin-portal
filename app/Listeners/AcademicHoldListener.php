<?php

namespace App\Listeners;

use App\Events\AcademicHoldPlaced;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AcademicHoldListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Handle the event.
     */
    public function handle(AcademicHoldPlaced $event): void
    {
        try {
            Log::info('Processing academic hold placed event', [
                'student_id' => $event->student->id,
                'hold_id' => $event->academicHold->id,
                'hold_type' => $event->academicHold->type,
            ]);

            // Send immediate notification to student about academic hold
            $result = $this->notificationService->processEventNotification(
                'academic_hold_placed',
                $event->getNotificationData()
            );

            Log::info('Academic hold notification sent', [
                'result' => $result,
                'student_id' => $event->student->id,
                'hold_id' => $event->academicHold->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process academic hold notification', [
                'student_id' => $event->student->id,
                'hold_id' => $event->academicHold->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Handle bulk academic hold placement
     */
    public function handleBulk(\Illuminate\Support\Collection $events): void
    {
        try {
            Log::info('Processing bulk academic hold placed events', [
                'events_count' => $events->count(),
            ]);

            // Group events by hold type for efficient processing
            $eventsByType = $events->groupBy(fn($event) => $event->academicHold->type);

            foreach ($eventsByType as $holdType => $typeEvents) {
                $result = $this->notificationService->processEventNotification(
                    'academic_hold_placed',
                    AcademicHoldPlaced::getBulkNotificationData($typeEvents)
                );

                Log::info('Bulk academic hold notification sent', [
                    'result' => $result,
                    'hold_type' => $holdType,
                    'students_count' => $typeEvents->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process bulk academic hold notifications', [
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
    public function failed(AcademicHoldPlaced $event, \Throwable $exception): void
    {
        Log::error('Academic hold listener job failed permanently', [
            'student_id' => $event->student->id,
            'hold_id' => $event->academicHold->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
