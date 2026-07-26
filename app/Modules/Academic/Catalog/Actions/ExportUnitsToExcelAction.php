<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Modules\Academic\Catalog\Support\UnitSpreadsheetExporter;

class ExportUnitsToExcelAction
{
    public function __construct(private readonly UnitSpreadsheetExporter $exporter) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): string
    {
        return $this->exporter->exportUnitsToExcel($filters);
    }
}
