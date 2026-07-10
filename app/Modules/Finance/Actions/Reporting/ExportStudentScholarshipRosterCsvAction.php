<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Reporting;

use App\Modules\Finance\Queries\Reporting\ExportStudentScholarshipRosterQuery;
use Illuminate\Support\Facades\File;

class ExportStudentScholarshipRosterCsvAction
{
    public function __construct(
        private readonly ExportStudentScholarshipRosterQuery $query,
    ) {}

    /**
     * @return array{path: string, row_count: int, export_type: string}
     */
    public function handle(string $outputPath): array
    {
        File::ensureDirectoryExists(dirname($outputPath));

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open output file: {$outputPath}");
        }

        fputcsv($handle, $this->query->headings());

        $rowCount = 0;
        foreach ($this->query->cursor() as $row) {
            fputcsv($handle, $this->query->formatRow($row));
            $rowCount++;
        }

        fclose($handle);

        return [
            'path' => $outputPath,
            'row_count' => $rowCount,
            'export_type' => 'roster',
        ];
    }
}
