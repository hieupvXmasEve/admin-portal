<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Academic\Queries\ExportStudentLifecycleReportQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportStudentLifecycleReportCommand extends Command
{
    protected $signature = 'export:student-lifecycle-report
                            {--output= : CSV output path (default: storage/app/reports/student-lifecycle-report-YYYY-MM-DD_HHMMSS.csv)}';

    protected $description = 'Export student lifecycle status and GC level per semester across all campuses as CSV';

    public function __construct(
        private readonly ExportStudentLifecycleReportQuery $query,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $outputPath = $this->option('output')
            ?: storage_path('app/reports/student-lifecycle-report-'.now()->format('Y-m-d_His').'.csv');

        File::ensureDirectoryExists(dirname($outputPath));

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $this->error("Unable to open output file: {$outputPath}");

            return self::FAILURE;
        }

        $this->info('Exporting student lifecycle report...');

        fputcsv($handle, $this->query->headings());

        $rowCount = 0;
        foreach ($this->query->rows() as $row) {
            fputcsv($handle, $row);
            $rowCount++;
        }

        fclose($handle);

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rows exported', (string) $rowCount],
                ['Output file', $outputPath],
            ],
        );

        $this->info('Export completed.');

        return self::SUCCESS;
    }
}
