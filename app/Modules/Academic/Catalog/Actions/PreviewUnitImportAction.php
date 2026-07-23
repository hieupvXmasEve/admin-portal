<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Services\UnitExcelImportService;
use Illuminate\Support\Facades\Storage;

class PreviewUnitImportAction
{
    public function __construct(private readonly UnitExcelImportService $imports) {}

    /** @return array<string, mixed> */
    public function handle(string $path, int $rows): array
    {
        return $this->imports->previewImportData($this->absolutePath($path), $rows);
    }

    private function absolutePath(string $path): string
    {
        if (! str_starts_with($path, 'temp/imports/')) {
            throw new \RuntimeException('Invalid import file path.');
        }

        $absolutePath = Storage::disk('local')->path($path);
        if (! is_file($absolutePath)) {
            throw new \RuntimeException('Import file not found.');
        }

        return $absolutePath;
    }
}
