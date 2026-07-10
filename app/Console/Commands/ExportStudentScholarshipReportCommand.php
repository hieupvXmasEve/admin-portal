<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Reporting\ExportStudentScholarshipApplicationCsvAction;
use App\Modules\Finance\Actions\Reporting\ExportStudentScholarshipRosterCsvAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportStudentScholarshipReportCommand extends Command
{
    protected $signature = 'export:student-scholarship-report
                            {--output-dir= : Directory for CSV files (default: storage/app/reports)}
                            {--roster-only : Export only the student scholarship roster CSV}
                            {--application-only : Export only the semester scholarship application CSV}';

    protected $description = 'Export student scholarship roster and per-semester application CSVs for AI analysis';

    public function __construct(
        private readonly ExportStudentScholarshipRosterCsvAction $rosterAction,
        private readonly ExportStudentScholarshipApplicationCsvAction $applicationAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rosterOnly = (bool) $this->option('roster-only');
        $applicationOnly = (bool) $this->option('application-only');

        if ($rosterOnly && $applicationOnly) {
            $this->error('Use only one of --roster-only or --application-only.');

            return self::FAILURE;
        }

        $outputDir = $this->option('output-dir') ?: storage_path('app/reports');
        File::ensureDirectoryExists($outputDir);

        $timestamp = now()->format('Y-m-d_His');
        $results = [];

        $this->info('Exporting student scholarship reports...');

        if (! $applicationOnly) {
            $results[] = $this->rosterAction->handle(
                $outputDir.'/student-scholarship-roster-'.$timestamp.'.csv',
            );
        }

        if (! $rosterOnly) {
            $results[] = $this->applicationAction->handle(
                $outputDir.'/student-scholarship-application-'.$timestamp.'.csv',
            );
        }

        $this->newLine();
        $this->table(
            ['Export', 'Rows', 'Output file'],
            array_map(
                static fn (array $result): array => [
                    $result['export_type'],
                    (string) $result['row_count'],
                    $result['path'],
                ],
                $results,
            ),
        );

        $this->info('Export completed.');

        return self::SUCCESS;
    }
}
