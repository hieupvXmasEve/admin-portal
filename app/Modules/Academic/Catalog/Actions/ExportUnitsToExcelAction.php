<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Services\UnitExcelExportService;

class ExportUnitsToExcelAction
{
    public function __construct(private readonly UnitExcelExportService $exportService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters): string
    {
        return $this->exportService->exportUnitsToExcel($filters);
    }
}
