<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Upload\Jobs\CleanupOrphanedFilesJob;
use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Console\Command;

class CleanupUploadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'uploads:cleanup
                            {--dry-run : Show what would be cleaned without actually deleting}
                            {--orphaned-age=24 : Age in hours for orphaned files}
                            {--expired-age=30 : Age in days for expired records}
                            {--chunked-age=2 : Age in hours for chunked uploads}
                            {--batch-size=100 : Batch size for processing}
                            {--queue : Run cleanup job in queue}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up orphaned files, expired records, and temporary files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = [
            'orphaned_file_age_hours' => (int) $this->option('orphaned-age'),
            'expired_record_age_days' => (int) $this->option('expired-age'),
            'chunked_upload_age_hours' => (int) $this->option('chunked-age'),
            'batch_size' => (int) $this->option('batch-size'),
            'dry_run' => $this->option('dry-run'),
        ];

        if ($this->option('dry-run')) {
            $this->info('Running in DRY RUN mode - no files will be deleted');
        }

        $this->info('Starting upload cleanup with configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Orphaned file age', $config['orphaned_file_age_hours'].' hours'],
                ['Expired record age', $config['expired_record_age_days'].' days'],
                ['Chunked upload age', $config['chunked_upload_age_hours'].' hours'],
                ['Batch size', $config['batch_size']],
                ['Dry run', $config['dry_run'] ? 'Yes' : 'No'],
            ]
        );

        if ($this->option('queue')) {
            $this->info('Dispatching cleanup job to queue...');
            CleanupOrphanedFilesJob::dispatch($config);
            $this->info('Cleanup job dispatched successfully');

            return 0;
        }

        $this->info('Running cleanup job synchronously...');

        try {
            app(UploadPlatform::class)->cleanup($config);

            $this->info('Cleanup completed successfully');

            return 0;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: '.$e->getMessage());

            return 1;
        }
    }
}
