<?php

namespace App\Console\Commands;

use App\Services\EventParticipationService;
use App\Services\EventService;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEventCompletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:process-completions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process automatic event completions and complete events that have ended';

    /**
     * Execute the console command.
     */
    public function handle(
        EventParticipationService $participationService,
        EventService $eventService
    ): int {
        $this->info('Processing event completions...');

        try {
            // Process automatic participant completions
            $participationsCompleted = $participationService->processAutomaticCompletions();

            // Complete events that have ended
            $eventsCompleted = $this->completeEndedEvents($eventService);

            // Process any failed gold rewards
            $this->info('Processing failed gold rewards...');
            $failedGoldProcessed = $participationService->processFailedGoldRewards();

            $this->info("Successfully completed {$participationsCompleted} participations, {$eventsCompleted} events, and processed {$failedGoldProcessed} failed gold rewards.");

            Log::info('Event completions command completed', [
                'participations_completed' => $participationsCompleted,
                'events_completed' => $eventsCompleted,
                'failed_gold_processed' => $failedGoldProcessed
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to process event completions: ' . $e->getMessage());

            Log::error('Event completions command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Complete events that have ended but are still published
     */
    private function completeEndedEvents(EventService $eventService): int
    {
        $endedEvents = Event::where('status', 'published')
            ->where('end_time', '<', now())
            ->get();

        $completedCount = 0;

        foreach ($endedEvents as $event) {
            try {
                $eventService->completeEvent($event);
                $completedCount++;

                $this->line("Completed event: {$event->title}");
            } catch (\Exception $e) {
                $this->warn("Failed to complete event {$event->id}: {$e->getMessage()}");

                Log::error('Failed to auto-complete event', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $completedCount;
    }
}
