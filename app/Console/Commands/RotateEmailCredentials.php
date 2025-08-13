<?php

namespace App\Console\Commands;

use App\Services\SmtpConfigurationService;
use Illuminate\Console\Command;

class RotateEmailCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:rotate-credentials
                            {--max-age=90 : Maximum age in days before rotation is required}
                            {--force : Force rotation even if not needed}
                            {--dry-run : Show what would be rotated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rotate encryption for email configuration credentials';

    /**
     * Execute the console command.
     */
    public function handle(SmtpConfigurationService $smtpService)
    {
        $maxAge = (int) $this->option('max-age');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info('Email Credential Rotation Tool');
        $this->info('================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual changes will be made');
        }

        // Get configurations that need rotation
        $configurations = $force ?
            $smtpService->getAll() :
            $smtpService->getConfigurationsNeedingRotation($maxAge);

        if ($configurations->isEmpty()) {
            $this->info('No email configurations need credential rotation.');
            return Command::SUCCESS;
        }

        $this->info("Found {$configurations->count()} configuration(s) that need rotation:");

        // Display configurations
        $tableData = [];
        foreach ($configurations as $config) {
            $metadata = $config->getEncryptionMetadata();
            $tableData[] = [
                $config->id,
                $config->name,
                $config->password_encrypted_at?->format('Y-m-d H:i:s') ?? 'Unknown',
                $metadata['needs_rotation'] ? 'Yes' : 'No',
                $config->is_active ? 'Active' : 'Inactive'
            ];
        }

        $this->table(
            ['ID', 'Name', 'Encrypted At', 'Needs Rotation', 'Status'],
            $tableData
        );

        if ($dryRun) {
            $this->info('Dry run complete. Use --force to perform actual rotation.');
            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (!$force && !$this->confirm('Do you want to proceed with credential rotation?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Perform rotation
        $this->info('Starting credential rotation...');
        $progressBar = $this->output->createProgressBar($configurations->count());

        $results = [
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($configurations as $config) {
            try {
                if ($smtpService->rotatePasswordEncryption($config)) {
                    $results['successful']++;
                    $this->line("\n✓ Rotated credentials for: {$config->name}");
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to rotate credentials for: {$config->name}";
                    $this->line("\n✗ Failed to rotate credentials for: {$config->name}");
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error rotating {$config->name}: " . $e->getMessage();
                $this->line("\n✗ Error rotating {$config->name}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Credential Rotation Results:');
        $this->info("Successful: {$results['successful']}");

        if ($results['failed'] > 0) {
            $this->error("Failed: {$results['failed']}");

            if (!empty($results['errors'])) {
                $this->error('Errors:');
                foreach ($results['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }
        }

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
