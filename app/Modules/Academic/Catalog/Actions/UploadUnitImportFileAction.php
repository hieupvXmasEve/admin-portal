<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Modules\Academic\Catalog\Support\UnitSpreadsheetImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadUnitImportFileAction
{
    public function __construct(private readonly UnitSpreadsheetImporter $imports) {}

    /**
     * @return array{file_path: string, filename: string, preview: array<string, mixed>}
     */
    public function handle(UploadedFile $file): array
    {
        $filename = Str::uuid()->toString().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('temp/imports', $filename, 'local');

        if ($path === false) {
            throw new \RuntimeException('Failed to store the import file.');
        }

        return [
            'file_path' => $path,
            'filename' => $file->getClientOriginalName(),
            'preview' => $this->imports->previewImportData(Storage::disk('local')->path($path), 5),
        ];
    }
}
