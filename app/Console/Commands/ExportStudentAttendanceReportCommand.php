<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Academic\Delivery\Queries\ExportStudentAttendanceReportQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportStudentAttendanceReportCommand extends Command
{
    protected $signature = 'export:student-attendance-report
                            {--output= : CSV output path (default: storage/app/reports/student-attendance-report-YYYY-MM-DD_HHMMSS.csv)}';

    protected $description = 'Export summary attendance and grade report per student per course per semester as CSV';

    public function __construct(
        private readonly ExportStudentAttendanceReportQuery $query,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $outputPath = $this->option('output')
            ?: storage_path('app/reports/student-attendance-report-'.now()->format('Y-m-d_His').'.csv');

        File::ensureDirectoryExists(dirname($outputPath));

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $this->error("Unable to open output file: {$outputPath}");

            return self::FAILURE;
        }

        $this->info('Exporting student attendance summary report...');

        fputcsv($handle, $this->query->headings());

        $rowCount = 0;
        foreach ($this->query->cursor() as $row) {
            fputcsv($handle, $this->query->formatRow($row));
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
