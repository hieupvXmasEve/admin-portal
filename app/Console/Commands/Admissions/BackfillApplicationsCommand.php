<?php

declare(strict_types=1);

namespace App\Console\Commands\Admissions;

use App\Services\Admissions\ApplicationBackfillService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Slice 09 — migrate existing `student_applications` rows into the decomposed
 * structure (guardians + documents + lifecycle status).
 *
 * Read-only by default: it prints what *would* change. Pass `--apply` to commit.
 * Run this before the `drop legacy parent/submitted columns` migration; that
 * migration refuses to run until this backfill has populated the target tables.
 */
class BackfillApplicationsCommand extends Command
{
    protected $signature = 'applications:backfill
        {--apply : Commit the backfill (default is a read-only dry-run)}
        {--file-types= : Path to the document-type catalog CSV}
        {--admission-files= : Path to the CRM admission-files CSV}';

    protected $description = 'Backfill legacy student_applications into guardians, documents, and the new lifecycle status (read-only by default)';

    public function handle(ApplicationBackfillService $service): int
    {
        $fileTypes = (string) ($this->option('file-types') ?: base_path('data/Asia_File_Types.csv'));
        $admissionFiles = (string) ($this->option('admission-files') ?: base_path('data/Asia_NE_2025_AdmissionFiles.csv'));
        $apply = (bool) $this->option('apply');

        $this->line('');
        $this->info(sprintf('🎓 Student-application backfill (%s)', $apply ? 'APPLY' : 'dry-run'));
        $this->comment("  catalog:    {$fileTypes}");
        $this->comment("  admissions: {$admissionFiles}");

        try {
            $report = $service->run($fileTypes, $admissionFiles, dryRun: ! $apply);
        } catch (Throwable $e) {
            $this->error('Backfill failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->table(['Metric', 'Count'], $report->toRows());

        if ($report->applied) {
            $this->info(sprintf(
                '✅ Applied: %d guardian(s), %d document(s) created; %d enrolled, %d pending.',
                $report->guardiansCreated,
                $report->documentsCreated(),
                $report->statusToEnrolled,
                $report->statusToPending,
            ));
            $this->comment('You may now run `php artisan migrate` to drop the legacy columns.');
        } else {
            $this->comment('Dry-run only — nothing was written. Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }
}
