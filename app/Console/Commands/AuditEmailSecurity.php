<?php

namespace App\Console\Commands;

use App\Services\SmtpConfigurationService;
use Illuminate\Console\Command;

class AuditEmailSecurity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:audit-security
                            {--format=table : Output format (table, json)}
                            {--export= : Export results to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit email configuration security and encryption status';

    /**
     * Execute the console command.
     */
    public function handle(SmtpConfigurationService $smtpService)
    {
        $format = $this->option('format');
        $exportFile = $this->option('export');

        $this->info('Email Security Audit Report');
        $this->info('==========================');

        // Get security audit report
        $report = $smtpService->getSecurityAuditReport();

        // Display summary
        $this->displaySummary($report);

        // Display detailed information
        if ($format === 'json') {
            $this->displayJsonReport($report);
        } else {
            $this->displayTableReport($report);
        }

        // Validate password integrity
        $this->info("\nPassword Integrity Check:");
        $integrityResults = $smtpService->validateAllPasswordIntegrity();
        $this->displayIntegrityResults($integrityResults);

        // Export if requested
        if ($exportFile) {
            $this->exportReport($report, $integrityResults, $exportFile);
        }

        // Return appropriate exit code
        $hasIssues = $report['configurations_needing_rotation'] > 0 ||
                    $report['configurations_with_integrity_issues'] > 0;

        return $hasIssues ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Display summary information
     */
    private function displaySummary(array $report): void
    {
        $this->info("Total Configurations: {$report['total_configurations']}");
        $this->info("Configurations with Passwords: {$report['configurations_with_passwords']}");

        if ($report['configurations_needing_rotation'] > 0) {
            $this->warn("Configurations Needing Rotation: {$report['configurations_needing_rotation']}");
        } else {
            $this->info("Configurations Needing Rotation: {$report['configurations_needing_rotation']}");
        }

        $this->info("Configurations with Backups: {$report['configurations_with_backups']}");

        if ($report['configurations_with_integrity_issues'] > 0) {
            $this->error("Configurations with Integrity Issues: {$report['configurations_with_integrity_issues']}");
        } else {
            $this->info("Configurations with Integrity Issues: {$report['configurations_with_integrity_issues']}");
        }
    }

    /**
     * Display report in table format
     */
    private function displayTableReport(array $report): void
    {
        if (empty($report['encryption_metadata'])) {
            $this->info("\nNo configurations with encryption metadata found.");
            return;
        }

        $this->info("\nDetailed Configuration Analysis:");

        $tableData = [];
        foreach ($report['encryption_metadata'] as $config) {
            $metadata = $config['metadata'];
            $tableData[] = [
                $config['id'],
                $config['name'],
                $metadata['encrypted_at'] ?? 'Unknown',
                $metadata['needs_rotation'] ? 'Yes' : 'No',
                $metadata['has_salt'] ? 'Yes' : 'No',
                $metadata['has_verification_hash'] ? 'Yes' : 'No',
                $metadata['encryption_algorithm'] ?? 'Unknown'
            ];
        }

        $this->table(
            ['ID', 'Name', 'Encrypted At', 'Needs Rotation', 'Has Salt', 'Has Verification', 'Algorithm'],
            $tableData
        );
    }

    /**
     * Display report in JSON format
     */
    private function displayJsonReport(array $report): void
    {
        $this->info("\nDetailed Report (JSON):");
        $this->line(json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Display password integrity results
     */
    private function displayIntegrityResults(array $results): void
    {
        $this->info("Total Checked: {$results['total']}");
        $this->info("Valid: {$results['valid']}");

        if ($results['invalid'] > 0) {
            $this->error("Invalid: {$results['invalid']}");

            if (!empty($results['invalid_configurations'])) {
                $this->error("Configurations with integrity issues:");
                foreach ($results['invalid_configurations'] as $config) {
                    $this->error("  - ID {$config['id']}: {$config['name']} (encrypted: {$config['encrypted_at']})");
                }
            }
        } else {
            $this->info("Invalid: {$results['invalid']}");
        }
    }

    /**
     * Export report to file
     */
    private function exportReport(array $report, array $integrityResults, string $filename): void
    {
        try {
            $exportData = [
                'generated_at' => now()->toISOString(),
                'security_audit' => $report,
                'integrity_check' => $integrityResults
            ];

            $content = json_encode($exportData, JSON_PRETTY_PRINT);
            file_put_contents($filename, $content);

            $this->info("\nReport exported to: {$filename}");

        } catch (\Exception $e) {
            $this->error("Failed to export report: " . $e->getMessage());
        }
    }
}
