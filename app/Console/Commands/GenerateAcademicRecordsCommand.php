<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AcademicRecordGenerationServiceOptimized;
use Illuminate\Console\Command;

class GenerateAcademicRecordsCommand extends Command
{
    protected $signature = 'academic-records:generate
                            {--update-existing : Update attendance stats for existing records}
                            {--dry-run : Run without making changes}';

    protected $description = 'Generate academic records for students with attendance records';

    public function __construct(
        private AcademicRecordGenerationServiceOptimized $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting academic record generation...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            if ($this->option('update-existing')) {
                $this->updateExistingRecords();
            } else {
                $this->generateNewRecords();
            }

            $executionTime = round(microtime(true) - $startTime, 2);
            $memoryUsed = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->newLine();
            $this->info("✓ Completed in {$executionTime} seconds");
            $this->info("📊 Memory used: {$memoryUsed}MB | Peak: {$memoryPeak}MB");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function generateNewRecords(): void
    {
        $this->info('Generating academic records from attendance data...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('This would create academic records for students with attendance.');

            return;
        }

        $progressBar = null;

        $stats = $this->service->generateAcademicRecords(
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

        $this->displayStats($stats, 'Academic Records Generation');

        if ($stats['created'] > 0) {
            $this->info("✓ Successfully created {$stats['created']} academic records");
        }

        if ($stats['skipped'] > 0) {
            $this->warn("⚠ Skipped {$stats['skipped']} records (already exist)");
        }

        if ($stats['errors'] > 0) {
            $this->error("✗ Failed to create {$stats['errors']} records");
        }
    }

    private function updateExistingRecords(): void
    {
        $this->info('Updating attendance statistics for existing academic records...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('This would update attendance stats for existing academic records.');

            return;
        }

        $progressBar = null;

        $stats = $this->service->updateAttendanceStatsForExistingRecords(
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

        $this->displayStats($stats, 'Attendance Stats Update');

        if ($stats['updated'] > 0) {
            $this->info("✓ Successfully updated {$stats['updated']} academic records");
        }

        if ($stats['errors'] > 0) {
            $this->error("✗ Failed to update {$stats['errors']} records");
        }
    }

    private function displayStats(array $stats, string $title): void
    {
        $this->table(
            [$title, 'Count'],
            [
                ['Created/Updated', $stats['created'] ?? $stats['updated'] ?? 0],
                ['Skipped', $stats['skipped'] ?? 0],
                ['Errors', $stats['errors']],
            ]
        );
    }
}
