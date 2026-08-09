<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\UploadRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadCleanupManager
{
    protected array $config;

    public function cleanup(UploadPlatform $uploadPlatform, array $config = []): array
    {
        $this->config = array_merge([
            'orphaned_file_age_hours' => 24,
            'expired_record_age_days' => 30,
            'chunked_upload_age_hours' => 2,
            'batch_size' => 100,
            'dry_run' => false,
        ], $config);

        Log::info('Starting upload cleanup', $this->config);

        $results = [
            'orphaned_files' => $this->cleanupOrphanedFiles(),
            'expired_records' => $this->cleanupExpiredRecords(),
            'chunked_uploads' => $uploadPlatform->cleanupExpiredSessions(),
            'temporary_files' => $this->cleanupTemporaryFiles(),
            'empty_directories' => $this->cleanupEmptyDirectories(),
        ];

        Log::info('Upload cleanup completed', $results);

        return $results;
    }

    /**
     * Clean up orphaned files (files without database records).
     */
    protected function cleanupOrphanedFiles(): array
    {
        $results = [
            'scanned_contexts' => 0,
            'orphaned_files' => 0,
            'cleaned_files' => 0,
            'failed_cleanups' => [],
            'total_size_freed' => 0,
        ];

        $contexts = config('uploads.contexts', []);
        $cutoffTime = Carbon::now()->subHours($this->config['orphaned_file_age_hours']);

        foreach ($contexts as $context => $contextConfig) {
            $results['scanned_contexts']++;

            try {
                $disk = $contextConfig['disk'] ?? 'public';
                $directory = $contextConfig['directory'] ?? 'uploads';

                Log::debug('Scanning context for orphaned files', [
                    'context' => $context,
                    'disk' => $disk,
                    'directory' => $directory,
                ]);

                $this->scanDirectoryForOrphanedFiles(
                    $disk,
                    $directory,
                    $cutoffTime,
                    $results
                );

            } catch (\Exception $e) {
                $results['failed_cleanups'][] = [
                    'type' => 'context_scan',
                    'context' => $context,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to scan context for orphaned files', [
                    'context' => $context,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Scan directory for orphaned files.
     */
    protected function scanDirectoryForOrphanedFiles(
        string $disk,
        string $directory,
        Carbon $cutoffTime,
        array &$results
    ): void {
        $files = Storage::disk($disk)->allFiles($directory);

        foreach ($files as $filePath) {
            try {
                // Check file age
                $lastModified = Carbon::createFromTimestamp(
                    Storage::disk($disk)->lastModified($filePath)
                );

                if ($lastModified->isAfter($cutoffTime)) {
                    continue; // File is too recent, skip
                }

                // Check if file has a corresponding database record
                $hasRecord = UploadRecord::where('disk', $disk)
                    ->where('path', $filePath)
                    ->exists();

                if (! $hasRecord) {
                    $results['orphaned_files']++;

                    if (! $this->config['dry_run']) {
                        $fileSize = Storage::disk($disk)->size($filePath);

                        if (Storage::disk($disk)->delete($filePath)) {
                            $results['cleaned_files']++;
                            $results['total_size_freed'] += $fileSize;

                            Log::info('Deleted orphaned file', [
                                'disk' => $disk,
                                'path' => $filePath,
                                'size' => $fileSize,
                                'last_modified' => $lastModified->toISOString(),
                            ]);
                        } else {
                            $results['failed_cleanups'][] = [
                                'type' => 'file_deletion',
                                'disk' => $disk,
                                'path' => $filePath,
                                'error' => 'Failed to delete file',
                            ];
                        }
                    } else {
                        Log::info('Would delete orphaned file (dry run)', [
                            'disk' => $disk,
                            'path' => $filePath,
                            'last_modified' => $lastModified->toISOString(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $results['failed_cleanups'][] = [
                    'type' => 'file_processing',
                    'disk' => $disk,
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                ];

                Log::error('Error processing file for orphan cleanup', [
                    'disk' => $disk,
                    'path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Clean up expired upload records.
     */
    protected function cleanupExpiredRecords(): array
    {
        $results = [
            'expired_records' => 0,
            'cleaned_records' => 0,
            'failed_cleanups' => [],
        ];

        $cutoffDate = Carbon::now()->subDays($this->config['expired_record_age_days']);

        try {
            // Find expired records in batches
            $query = UploadRecord::where('created_at', '<', $cutoffDate)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', Carbon::now());

            $results['expired_records'] = $query->count();

            if (! $this->config['dry_run']) {
                $query->chunk($this->config['batch_size'], function ($records) use (&$results) {
                    foreach ($records as $record) {
                        try {
                            // Delete the file first
                            if ($record->deleteFile()) {
                                // Then delete the record
                                if ($record->delete()) {
                                    $results['cleaned_records']++;

                                    Log::info('Deleted expired upload record', [
                                        'upload_id' => $record->id,
                                        'filename' => $record->filename,
                                        'context' => $record->context,
                                        'expired_at' => $record->expires_at,
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            $results['failed_cleanups'][] = [
                                'type' => 'expired_record',
                                'upload_id' => $record->id,
                                'error' => $e->getMessage(),
                            ];

                            Log::error('Failed to delete expired upload record', [
                                'upload_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
            } else {
                Log::info('Would delete expired records (dry run)', [
                    'count' => $results['expired_records'],
                ]);
            }

        } catch (\Exception $e) {
            $results['failed_cleanups'][] = [
                'type' => 'expired_records_query',
                'error' => $e->getMessage(),
            ];

            Log::error('Failed to query expired records', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Clean up temporary files.
     */
    protected function cleanupTemporaryFiles(): array
    {
        $results = [
            'temp_files' => 0,
            'cleaned_files' => 0,
            'failed_cleanups' => [],
            'total_size_freed' => 0,
        ];

        $tempDir = sys_get_temp_dir();
        $cutoffTime = Carbon::now()->subHours($this->config['chunked_upload_age_hours']);

        try {
            $pattern = $tempDir.'/*_assembled';
            $tempFiles = glob($pattern);

            foreach ($tempFiles as $tempFile) {
                try {
                    $lastModified = Carbon::createFromTimestamp(filemtime($tempFile));

                    if ($lastModified->isBefore($cutoffTime)) {
                        $results['temp_files']++;

                        if (! $this->config['dry_run']) {
                            $fileSize = filesize($tempFile);

                            if (unlink($tempFile)) {
                                $results['cleaned_files']++;
                                $results['total_size_freed'] += $fileSize;

                                Log::info('Deleted temporary file', [
                                    'path' => $tempFile,
                                    'size' => $fileSize,
                                    'last_modified' => $lastModified->toISOString(),
                                ]);
                            } else {
                                $results['failed_cleanups'][] = [
                                    'type' => 'temp_file_deletion',
                                    'path' => $tempFile,
                                    'error' => 'Failed to delete temporary file',
                                ];
                            }
                        } else {
                            Log::info('Would delete temporary file (dry run)', [
                                'path' => $tempFile,
                                'last_modified' => $lastModified->toISOString(),
                            ]);
                        }
                    }

                } catch (\Exception $e) {
                    $results['failed_cleanups'][] = [
                        'type' => 'temp_file_processing',
                        'path' => $tempFile,
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            $results['failed_cleanups'][] = [
                'type' => 'temp_files_scan',
                'error' => $e->getMessage(),
            ];

            Log::error('Failed to scan temporary files', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Clean up empty directories.
     */
    protected function cleanupEmptyDirectories(): array
    {
        $results = [
            'empty_directories' => 0,
            'cleaned_directories' => 0,
            'failed_cleanups' => [],
        ];

        $contexts = config('uploads.contexts', []);

        foreach ($contexts as $context => $contextConfig) {
            try {
                $disk = $contextConfig['disk'] ?? 'public';
                $directory = $contextConfig['directory'] ?? 'uploads';

                $this->cleanupEmptyDirectoriesRecursive($disk, $directory, $results);

            } catch (\Exception $e) {
                $results['failed_cleanups'][] = [
                    'type' => 'directory_cleanup',
                    'context' => $context,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Recursively clean up empty directories.
     */
    protected function cleanupEmptyDirectoriesRecursive(
        string $disk,
        string $directory,
        array &$results
    ): void {
        try {
            $subdirectories = Storage::disk($disk)->directories($directory);

            // First, clean up subdirectories
            foreach ($subdirectories as $subdirectory) {
                $this->cleanupEmptyDirectoriesRecursive($disk, $subdirectory, $results);
            }

            // Then check if current directory is empty
            $files = Storage::disk($disk)->files($directory);
            $directories = Storage::disk($disk)->directories($directory);

            if (empty($files) && empty($directories)) {
                $results['empty_directories']++;

                if (! $this->config['dry_run']) {
                    if (Storage::disk($disk)->deleteDirectory($directory)) {
                        $results['cleaned_directories']++;

                        Log::info('Deleted empty directory', [
                            'disk' => $disk,
                            'directory' => $directory,
                        ]);
                    } else {
                        $results['failed_cleanups'][] = [
                            'type' => 'empty_directory_deletion',
                            'disk' => $disk,
                            'directory' => $directory,
                            'error' => 'Failed to delete empty directory',
                        ];
                    }
                } else {
                    Log::info('Would delete empty directory (dry run)', [
                        'disk' => $disk,
                        'directory' => $directory,
                    ]);
                }
            }

        } catch (\Exception $e) {
            $results['failed_cleanups'][] = [
                'type' => 'directory_scan',
                'disk' => $disk,
                'directory' => $directory,
                'error' => $e->getMessage(),
            ];
        }
    }
}
