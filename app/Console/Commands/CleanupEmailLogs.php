<?php

namespace App\Console\Commands;

use App\Services\EmailLoggingService;
use Illuminate\Console\Command;

class CleanupEmailLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:cleanup-logs
                            {--successful-days=90 : Days to keep successful email logs}
                            {--failed-days=365 : Days to keep failed email logs}
                            {--batch-size=1000 : Number of records to process at once}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old email logs based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(EmailLoggingService $loggingService)
    {
        $this->info('Starting email logs cleanup...');

        $retentionPolicy = [
            'successful_emails_days' => (int) $this->option('successful-days'),
            'failed_emails_days' => (int) $this->option('failed-days'),
            'batch_size' => (int) $this->option('batch-size'),
            'dry_run' => $this->option('dry-run'),
        ];

        if ($retentionPolicy['dry_run']) {
            $this->warn('DRY RUN MODE - No records will actually be deleted');
        }

        $this->table(
            ['Setting', 'Value'],
            [
                ['Successful emails retention', $retentionPolicy['successful_emails_days'] . ' days'],
                ['Failed emails retention', $retentionPolicy['failed_emails_days'] . ' days'],
                ['Batch size', $retentionPolicy['batch_size']],
                ['Mode', $retentionPolicy['dry_run'] ? 'Dry Run' : 'Live'],
            ]
        );

        if (!$retentionPolicy['dry_run']) {
            if (!$this->confirm('Are you sure you want to proceed with deleting email logs?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $results = $loggingService->cleanupOldLogs($retentionPolicy);

            $this->info('Cleanup completed successfully!');

            $this->table(
                ['Category', 'Records Processed'],
                [
                    ['Successful emails', number_format($results['successful_deleted'])],
                    ['Failed emails', number_format($results['failed_deleted'])],
                    ['Total', number_format($results['successful_deleted'] + $results['failed_deleted'])],
                ]
            );

            if (!empty($results['errors'])) {
                $this->error('Errors encountered during cleanup:');
                foreach ($results['errors'] as $error) {
                    $this->error('- ' . $error);
                }
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }
}
