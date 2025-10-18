<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AcademicRecordGenerationServiceOptimized;
use Illuminate\Console\Command;

class SyncAcademicRecordsCommand extends Command
{
    protected $signature = 'academic-records:sync
                            {--dry-run : Run without making changes}';

    protected $description = 'Sync academic records: create new and update existing attendance data (optimized for daily cron)';

    public function __construct(
        private AcademicRecordGenerationServiceOptimized $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting academic records sync...');
        $this->info('This will create new records and update existing ones with latest attendance data.');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
            return self::SUCCESS;
        }

        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            $progressBar = null;

            $stats = $this->service->syncAcademicRecords(
                function ($current, $total, $message) use (&$progressBar) {
                    if ($progressBar === null) {
                        $progressBar = $this->output->createProgressBar($total);
                        $progressBar->setFormat('verbose');
                    }
                    $progressBar->setProgress($current);
                    $progressBar->setMessage($message);
                }
            );

            if ($progressBar) {
                $progressBar->finish();
                $this->newLine();
            }

            $this->newLine();
            $this->displayStats($stats);

            $executionTime = round(microtime(true) - $startTime, 2);
            $memoryUsed = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->newLine();
            $this->info("✓ Sync completed in {$executionTime} seconds");
            $this->info("📊 Memory used: {$memoryUsed}MB | Peak: {$memoryPeak}MB");

            // Show summary
            if ($stats['created'] > 0) {
                $this->info("✓ Created {$stats['created']} new academic records");
            }
            if ($stats['updated'] > 0) {
                $this->info("✓ Updated {$stats['updated']} existing academic records");
            }
            if ($stats['skipped'] > 0) {
                $this->warn("⚠ Skipped {$stats['skipped']} records (no attendance data or invalid)");
            }
            if ($stats['errors'] > 0) {
                $this->error("✗ Failed to process {$stats['errors']} records");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function displayStats(array $stats): void
    {
        $this->table(
            ['Academic Records Sync', 'Count'],
            [
                ['Created (new)', $stats['created']],
                ['Updated (existing)', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Total Processed', $stats['total_processed']],
            ]
        );
    }
}
