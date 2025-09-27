<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EventParticipationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessFailedGoldRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:process-failed-gold-rewards
                            {--dry-run : Show what would be processed without making changes}
                            {--limit=100 : Maximum number of rewards to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process failed gold reward distributions and retry awarding gold to eligible participants';

    /**
     * Execute the console command.
     */
    public function handle(EventParticipationService $participationService): int
    {
        $this->info('Starting failed gold rewards processing...');

        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        try {
            if ($dryRun) {
                $this->info('DRY RUN MODE - No changes will be made');

                // Count failed rewards without processing
                $failedCount = $this->countFailedGoldRewards();
                $this->info("Found {$failedCount} failed gold rewards that would be processed.");

                return Command::SUCCESS;
            }

            // Process failed gold rewards
            $processedCount = $participationService->processFailedGoldRewards();

            $this->info("Successfully processed {$processedCount} failed gold rewards.");

            Log::info('Failed gold rewards command completed', [
                'processed_count' => $processedCount,
                'dry_run' => $dryRun,
                'limit' => $limit
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to process gold rewards: ' . $e->getMessage());

            Log::error('Failed gold rewards command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Count failed gold rewards for dry run.
     */
    private function countFailedGoldRewards(): int
    {
        return \App\Models\EventParticipant::where('status', 'completed')
            ->where('gold_awarded', false)
            ->whereHas('event', function ($query) {
                $query->where('gold_reward_amount', '>', 0)
                    ->where('end_time', '<', now());
            })
            ->count();
    }
}
