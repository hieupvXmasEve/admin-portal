<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Modules\Academic\Catalog\Support\UnitSpreadsheetImporter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessUnitImportAction
{
    public function __construct(private readonly UnitSpreadsheetImporter $imports) {}

    /**
     * @param  array{duplicate_handling: 'skip'|'update'|'error', create_prerequisites: bool, create_equivalents: bool}  $options
     * @return array<string, mixed>
     */
    public function handle(string $path, array $options, ?int $actorId = null): array
    {
        $absolutePath = $this->absolutePath($path);
        $startedAt = microtime(true);

        try {
            $sheetNames = array_map(
                static fn ($sheet): string => $sheet->getTitle(),
                IOFactory::load($absolutePath)->getAllSheets(),
            );
            $hasSyllabusSheets = array_intersect(
                ['Syllabus', 'Assessment Components', 'Assessment Details'],
                $sheetNames,
            ) !== [];

            $result = $hasSyllabusSheets
                ? $this->imports->importCombinedUnitsWithSyllabus($absolutePath, $options)
                : $this->imports->importUnitsFromExcel($absolutePath, $options);
            $result['summary']['processing_time'] = round(microtime(true) - $startedAt, 2).' seconds';

            Log::info('Catalog unit import completed.', [
                'actor_id' => $actorId,
                'summary' => $result['summary'],
            ]);

            return $result;
        } finally {
            Storage::disk('local')->delete($path);
        }
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
