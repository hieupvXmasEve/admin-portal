<?php

declare(strict_types=1);

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reusable service for handling Excel exports across the application
 */
class ExcelExportService
{
    /**
     * Export data to Excel and download
     *
     * @param  object  $exportClass  The export class instance (must implement Maatwebsite\Excel\Concerns\FromCollection or FromQuery)
     * @param  string  $filename  The filename for the downloaded file (without extension)
     * @param  string  $writerType  The export format (xlsx, csv, etc.)
     * @return BinaryFileResponse
     */
    public function download(object $exportClass, string $filename, string $writerType = 'xlsx'): BinaryFileResponse
    {
        $filename = $this->sanitizeFilename($filename);
        $filename = "{$filename}.{$writerType}";

        return Excel::download($exportClass, $filename);
    }

    /**
     * Export data to Excel and store on disk
     *
     * @param  object  $exportClass  The export class instance
     * @param  string  $path  The storage path (relative to storage/app)
     * @param  string  $disk  The storage disk to use
     * @param  string  $writerType  The export format
     * @return bool
     */
    public function store(object $exportClass, string $path, string $disk = 'local', string $writerType = 'xlsx'): bool
    {
        $path = $this->sanitizeFilename($path);

        if (! str_ends_with($path, ".{$writerType}")) {
            $path = "{$path}.{$writerType}";
        }

        return Excel::store($exportClass, $path, $disk);
    }

    /**
     * Export data to Excel as raw content
     *
     * @param  object  $exportClass  The export class instance
     * @param  string  $writerType  The export format
     * @return string
     */
    public function raw(object $exportClass, string $writerType = 'xlsx'): string
    {
        return Excel::raw($exportClass, $writerType);
    }

    /**
     * Generate a filename with timestamp
     *
     * @param  string  $prefix  The filename prefix
     * @param  string  $extension  The file extension (without dot)
     * @return string
     */
    public function generateFilenameWithTimestamp(string $prefix, string $extension = 'xlsx'): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $cleanPrefix = $this->sanitizeFilename($prefix);

        return "{$cleanPrefix}_{$timestamp}.{$extension}";
    }

    /**
     * Sanitize filename to remove invalid characters
     *
     * @param  string  $filename  The filename to sanitize
     * @return string
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove file extension if present
        $filename = preg_replace('/\.[^.]+$/', '', $filename);

        // Replace invalid characters with underscores
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Trim underscores from start and end
        return trim($filename, '_');
    }

    /**
     * Get MIME type for export format
     *
     * @param  string  $writerType  The export format
     * @return string
     */
    public function getMimeType(string $writerType): string
    {
        return match ($writerType) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'html' => 'text/html',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
