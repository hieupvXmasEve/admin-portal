<?php

namespace App\Services;

use App\Models\UploadRecord;
use App\Services\FileValidationService;
use App\Services\UploadUrlService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

class ImageUploadService
{
    /**
     * Upload contexts configuration.
     */
    protected array $contexts;

    /**
     * Default upload configuration.
     */
    protected array $defaults;

    /**
     * Security configuration.
     */
    protected array $security;

    /**
     * Performance configuration.
     */
    protected array $performance;

    /**
     * File validation service.
     */
    protected FileValidationService $validationService;

    /**
     * Upload URL service.
     */
    protected UploadUrlService $urlService;

    /**
     * Memory optimized file service.
     */
    protected MemoryOptimizedFileService $memoryService;

    public function __construct(
        FileValidationService $validationService,
        UploadUrlService $urlService,
        MemoryOptimizedFileService $memoryService
    ) {
        $this->contexts = config('uploads.contexts', []);
        $this->defaults = config('uploads.defaults', []);
        $this->security = config('uploads.security', []);
        $this->performance = config('uploads.performance', []);
        $this->validationService = $validationService;
        $this->urlService = $urlService;
        $this->memoryService = $memoryService;
    }

    /**
     * Upload a file for a specific context.
     */
    public function upload(
        UploadedFile $file,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = []
    ): UploadRecord {
        // Validate context
        $this->validateContext($context);

        // Get context configuration
        $config = $this->getContextConfig($context);
        // Validate file using comprehensive validation service
        $this->validationService->validateFile($file, $config);

        // Generate filename and path
        $filename = $this->generateFilename($file, $config);
        $path = $this->generatePath($filename, $config);

        // Store file
        $disk = $config['disk'];
        $storedPath = Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        if (!$storedPath) {
            throw new RuntimeException('Failed to store uploaded file');
        }
        // Generate URL
        $url = $this->generateUrl($storedPath, $disk, $config);

        $associationKeys = ['response_id', 'answer_id', 'ticket_id', 'reply_id'];
        $associations = Arr::only($metadata, $associationKeys);
        $metadata = Arr::except($metadata, $associationKeys);

        // Create upload record
        $uploadRecord = UploadRecord::create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'context' => $context,
            'path' => $storedPath,
            'disk' => $disk,
            'url' => $url,
            'hash' => $this->generateFileHash($file),
            'user_id' => $userId,
            'student_id' => $studentId,
            'response_id' => $associations['response_id'] ?? null,
            'answer_id' => $associations['answer_id'] ?? null,
            'ticket_id' => $associations['ticket_id'] ?? null,
            'reply_id' => $associations['reply_id'] ?? null,
            'metadata' => $metadata ?: null,
        ]);

        Log::info('File uploaded successfully', [
            'upload_id' => $uploadRecord->id,
            'context' => $context,
            'filename' => $filename,
            'size' => $file->getSize(),
            'user_id' => $userId,
            'student_id' => $studentId,
        ]);

        return $uploadRecord;
    }

    /**
     * Upload multiple files for a specific context.
     */
    public function uploadMultiple(
        array $files,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = []
    ): array {
        $uploadRecords = [];
        $failedUploads = [];

        foreach ($files as $index => $file) {
            try {
                $uploadRecords[] = $this->upload($file, $context, $userId, $studentId, $metadata);
            } catch (\Exception $e) {
                $failedUploads[$index] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to upload file', [
                    'context' => $context,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                    'student_id' => $studentId,
                ]);
            }
        }

        return [
            'successful' => $uploadRecords,
            'failed' => $failedUploads,
        ];
    }

    /**
     * Generate a unique filename for the uploaded file.
     */
    protected function generateFilename(UploadedFile $file, array $config): string
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        if ($this->security['generate_unique_names'] ?? true) {
            // Generate unique filename with timestamp and random string
            $timestamp = now()->format('Y-m-d_H-i-s');
            $random = Str::random(8);
            $sanitizedName = $this->sanitizeFilename(pathinfo($originalName, PATHINFO_FILENAME));

            return "{$timestamp}_{$random}_{$sanitizedName}.{$extension}";
        }

        // Use original filename with sanitization
        return $this->sanitizeFilename($originalName);
    }

    /**
     * Sanitize filename to prevent security issues.
     */
    protected function sanitizeFilename(string $filename): string
    {
        if (!($this->security['sanitize_filename'] ?? true)) {
            return $filename;
        }

        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Remove leading/trailing underscores and dots
        $filename = trim($filename, '_.');

        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'file_' . Str::random(8);
        }

        return $filename;
    }

    /**
     * Generate the storage path for the file.
     */
    protected function generatePath(string $filename, array $config): string
    {
        $directory = $config['directory'] ?? 'uploads';

        // Add date-based subdirectory for organization
        $dateDirectory = now()->format('Y/m');

        return "{$directory}/{$dateDirectory}/{$filename}";
    }

    /**
     * Generate file hash for integrity checking.
     */
    protected function generateFileHash(UploadedFile $file): string
    {
        // Use memory-optimized hash calculation for large files
        if ($file->getSize() > $this->performance['hash_streaming_threshold'] ?? (10 * 1024 * 1024)) {
            return $this->memoryService->calculateHash($file->getRealPath());
        }

        return hash_file('sha256', $file->getRealPath());
    }

    /**
     * Validate the upload context.
     */
    protected function validateContext(string $context): void
    {
        if (!isset($this->contexts[$context])) {
            throw new InvalidArgumentException("Invalid upload context: {$context}");
        }
    }

    /**
     * Get configuration for a specific context.
     */
    protected function getContextConfig(string $context): array
    {
        $contextConfig = $this->contexts[$context] ?? [];

        // Merge with defaults
        return array_merge($this->defaults, $contextConfig);
    }

    /**
     * Get available upload contexts.
     */
    public function getAvailableContexts(): array
    {
        return array_keys($this->contexts);
    }

    /**
     * Get configuration for a specific context.
     */
    public function getContextConfiguration(string $context): array
    {
        $this->validateContext($context);
        return $this->getContextConfig($context);
    }

    /**
     * Check if a context allows public access.
     */
    public function isContextPublic(string $context): bool
    {
        $config = $this->getContextConfig($context);
        return $config['public'] ?? true;
    }

    /**
     * Get maximum file size for a context in bytes.
     */
    public function getMaxFileSize(string $context): int
    {
        $config = $this->getContextConfig($context);
        return ($config['max_size'] ?? 10240) * 1024; // Convert KB to bytes
    }

    /**
     * Get allowed MIME types for a context.
     */
    public function getAllowedMimeTypes(string $context): array
    {
        $config = $this->getContextConfig($context);
        return $config['allowed_types'] ?? [];
    }

    /**
     * Get allowed file extensions for a context.
     */
    public function getAllowedExtensions(string $context): array
    {
        $config = $this->getContextConfig($context);
        return $config['allowed_extensions'] ?? [];
    }

    /**
     * Generate URL for uploaded file.
     */
    protected function generateUrl(string $path, string $disk, array $config): string
    {
        if ($config['public'] ?? true) {
            return $this->generatePublicUrl($path, $disk);
        }

        // For private files, return empty string - URLs will be generated on demand
        return '';
    }

    /**
     * Generate public URL for a file.
     */
    public function generatePublicUrl(string $path, string $disk): string
    {
        try {
            return Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            Log::error('Failed to generate public URL', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Generate temporary URL for private files.
     */
    public function generateTemporaryUrl(UploadRecord $uploadRecord, int $expirationMinutes = 60): string
    {
        $expiration = now()->addMinutes($expirationMinutes);
        $disk = Storage::disk($uploadRecord->disk);

        // Try native temporary URL if disk supports it (e.g., S3)
        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($uploadRecord->path, $expiration);
            } catch (\Exception $e) {
                // Driver doesn't actually support temporary URLs, fallback to signed route
                Log::debug('Disk temporaryUrl not supported, using signed route', [
                    'upload_id' => $uploadRecord->id,
                    'disk' => $uploadRecord->disk,
                ]);
            }
        }

        // Fallback to signed URL via uploads.serve route
        return $this->generateSignedUrl($uploadRecord, $expirationMinutes);
    }

    /**
     * Generate signed URL for secure access to private files.
     */
    public function generateSignedUrl(UploadRecord $uploadRecord, int $expirationMinutes = 60): string
    {
        try {
            $expiration = now()->addMinutes($expirationMinutes);

            return URL::temporarySignedRoute(
                'uploads.serve',
                $expiration,
                ['id' => $uploadRecord->id]
            );
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Verify signed URL signature.
     */
    public function verifySignedUrl(int $uploadId, int $expires, string $signature): bool
    {
        try {
            // Check if URL has expired
            if ($expires < now()->timestamp) {
                return false;
            }

            $uploadRecord = UploadRecord::find($uploadId);
            if (!$uploadRecord) {
                return false;
            }

            $expectedSignature = hash_hmac('sha256', $uploadId . $uploadRecord->path . $expires, config('app.key'));

            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Failed to verify signed URL', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get URL for an upload record based on its visibility.
     */
    public function getUrl(UploadRecord $uploadRecord, int $temporaryExpirationMinutes = 60): string
    {
        $config = $this->getContextConfig($uploadRecord->context);

        if ($config['public'] ?? true) {
            // For public files, return the stored URL or generate a new one
            return $uploadRecord->url ?: $this->generatePublicUrl($uploadRecord->path, $uploadRecord->disk);
        }

        // For private files, generate a temporary URL
        return $this->generateTemporaryUrl($uploadRecord, $temporaryExpirationMinutes);
    }

    /**
     * Get multiple URLs for upload records.
     */
    public function getUrls(array $uploadRecords, int $temporaryExpirationMinutes = 60): array
    {
        $urls = [];

        foreach ($uploadRecords as $uploadRecord) {
            $urls[$uploadRecord->id] = $this->getUrl($uploadRecord, $temporaryExpirationMinutes);
        }

        return $urls;
    }

    /**
     * Check if a storage driver supports temporary URLs.
     */
    public function supportsTemporaryUrls(string $disk): bool
    {
        try {
            $driver = Storage::disk($disk);
            return method_exists($driver, 'temporaryUrl');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get storage driver information.
     */
    public function getStorageDriverInfo(string $disk): array
    {
        try {
            $config = config("filesystems.disks.{$disk}");
            $driver = Storage::disk($disk);

            return [
                'disk' => $disk,
                'driver' => $config['driver'] ?? 'unknown',
                'supports_temporary_urls' => $this->supportsTemporaryUrls($disk),
                'supports_public_urls' => method_exists($driver, 'url'),
                'config' => $config,
            ];
        } catch (\Exception $e) {
            return [
                'disk' => $disk,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete an upload record and its associated file.
     */
    public function delete(UploadRecord $uploadRecord): bool
    {
        try {
            // Delete file from storage
            $fileDeleted = $uploadRecord->deleteFile();

            // Delete database record
            $recordDeleted = $uploadRecord->delete();

            Log::info('Upload deleted successfully', [
                'upload_id' => $uploadRecord->id,
                'context' => $uploadRecord->context,
                'filename' => $uploadRecord->filename,
            ]);

            return $fileDeleted && $recordDeleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete upload', [
                'upload_id' => $uploadRecord->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clean up failed uploads and orphaned files.
     */
    public function cleanup(): array
    {
        $results = [
            'expired_records' => 0,
            'orphaned_files' => 0,
            'failed_cleanups' => [],
        ];

        // Clean up expired records
        $expiredRecords = UploadRecord::expired()->get();
        foreach ($expiredRecords as $record) {
            try {
                if ($this->delete($record)) {
                    $results['expired_records']++;
                }
            } catch (\Exception $e) {
                $results['failed_cleanups'][] = [
                    'type' => 'expired_record',
                    'id' => $record->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Clean up orphaned files (files without database records)
        foreach ($this->contexts as $context => $config) {
            try {
                $disk = $config['disk'];
                $directory = $config['directory'];

                $files = Storage::disk($disk)->allFiles($directory);
                foreach ($files as $file) {
                    $exists = UploadRecord::where('disk', $disk)
                        ->where('path', $file)
                        ->exists();

                    if (!$exists) {
                        Storage::disk($disk)->delete($file);
                        $results['orphaned_files']++;
                    }
                }
            } catch (\Exception $e) {
                $results['failed_cleanups'][] = [
                    'type' => 'orphaned_files',
                    'context' => $context,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Upload cleanup completed', $results);

        return $results;
    }

    /**
     * Clean up a failed upload attempt.
     */
    public function cleanupFailedUpload(string $path, string $disk): void
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);

                Log::info('Cleaned up failed upload', [
                    'path' => $path,
                    'disk' => $disk,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup failed upload', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate file with detailed error information.
     */
    public function validateFileWithDetails(UploadedFile $file, string $context): array
    {
        try {
            $config = $this->getContextConfig($context);
            $this->validationService->validateFile($file, $config);

            return [
                'valid' => true,
                'errors' => [],
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'file_info' => [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ],
            ];
        }
    }

    /**
     * Get file signature information for debugging.
     */
    public function getFileSignatureInfo(UploadedFile $file): array
    {
        return $this->validationService->getFileSignature($file);
    }

    /**
     * Generate URL for an upload record.
     */
    public function generateUrlForRecord(UploadRecord $uploadRecord, array $options = []): string
    {
        return $this->urlService->generateUrl($uploadRecord, $options);
    }

    /**
     * Generate thumbnail URL for an upload record.
     */
    public function generateThumbnailUrl(UploadRecord $uploadRecord, array $dimensions = []): string
    {
        return $this->urlService->generateThumbnailUrl($uploadRecord, $dimensions);
    }

    /**
     * Generate responsive URLs for different screen sizes.
     */
    public function generateResponsiveUrls(UploadRecord $uploadRecord): array
    {
        return $this->urlService->generateResponsiveUrls($uploadRecord);
    }

    /**
     * Generate batch URLs for multiple upload records.
     */
    public function generateBatchUrls(array $uploadRecords, array $options = []): array
    {
        return $this->urlService->generateBatchUrls($uploadRecords, $options);
    }

    /**
     * Get URL metadata including expiration and access type.
     */
    public function getUrlMetadata(string $url): array
    {
        return $this->urlService->getUrlMetadata($url);
    }

    /**
     * Validate URL signature for secure access.
     */
    public function validateUrlSignature(string $url): bool
    {
        return $this->urlService->validateSignature($url);
    }

    /**
     * Get performance statistics.
     */
    public function getPerformanceStats(): array
    {
        return [
            'memory_stats' => $this->memoryService->getMemoryStats(),
            'upload_contexts' => count($this->contexts),
            'performance_config' => $this->performance,
            'security_config' => $this->security,
        ];
    }

    /**
     * Optimize memory usage.
     */
    public function optimizeMemory(): array
    {
        return $this->memoryService->optimizeMemory();
    }

    /**
     * Process file with memory optimization.
     */
    public function processFileOptimized(UploadedFile $file, callable $processor): mixed
    {
        return $this->memoryService->processFile($file, $processor);
    }

    /**
     * Validate file with comprehensive integrity check.
     */
    public function validateFileIntegrity(UploadedFile $file): array
    {
        return $this->memoryService->validateFileIntegrity($file);
    }

    /**
     * Get chunked upload configuration.
     */
    public function getChunkedUploadConfig(): array
    {
        return [
            'enabled' => config('uploads.chunked.enabled', true),
            'chunk_size' => config('uploads.chunked.chunk_size', 5 * 1024 * 1024),
            'max_file_size' => config('uploads.chunked.max_file_size', 100 * 1024 * 1024),
            'expiration_minutes' => config('uploads.chunked.expiration_minutes', 60),
        ];
    }

    /**
     * Check if file should use chunked upload.
     */
    public function shouldUseChunkedUpload(int $fileSize): bool
    {
        $chunkedConfig = $this->getChunkedUploadConfig();

        if (!$chunkedConfig['enabled']) {
            return false;
        }

        $threshold = config('uploads.chunked.threshold', 10 * 1024 * 1024); // 10MB default
        return $fileSize > $threshold;
    }
}
