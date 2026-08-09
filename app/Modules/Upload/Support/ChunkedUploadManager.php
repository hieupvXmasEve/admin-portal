<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\UploadRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ChunkedUploadManager
{
    /**
     * Chunk size in bytes (default 5MB).
     */
    protected int $chunkSize;

    /**
     * Maximum file size for chunked uploads (default 100MB).
     */
    protected int $maxFileSize;

    /**
     * Chunk storage disk.
     */
    protected string $chunkDisk;

    /**
     * Chunk expiration time in minutes.
     */
    protected int $chunkExpiration;

    /**
     * Image upload service.
     */
    protected UploadManager $imageUploadService;

    /**
     * File validation service.
     */
    protected FileValidator $validationService;

    public function __construct(
        UploadManager $imageUploadService,
        FileValidator $validationService
    ) {
        $this->chunkSize = config('uploads.chunked.chunk_size', 5 * 1024 * 1024); // 5MB
        $this->maxFileSize = config('uploads.chunked.max_file_size', 100 * 1024 * 1024); // 100MB
        $this->chunkDisk = config('uploads.chunked.disk', 'local');
        $this->chunkExpiration = config('uploads.chunked.expiration_minutes', 60);
        $this->imageUploadService = $imageUploadService;
        $this->validationService = $validationService;
    }

    /**
     * Initialize a chunked upload session.
     */
    public function initializeUpload(
        string $filename,
        int $fileSize,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = []
    ): array {
        // Validate file size
        if ($fileSize > $this->maxFileSize) {
            throw new InvalidArgumentException(
                'File size exceeds maximum allowed size for chunked uploads: '.
                number_format($this->maxFileSize / (1024 * 1024), 2).'MB'
            );
        }

        if ($fileSize <= 0) {
            throw new InvalidArgumentException('Invalid file size');
        }

        // Generate upload session ID
        $uploadId = Str::uuid()->toString();

        // Calculate number of chunks
        $totalChunks = (int) ceil($fileSize / $this->chunkSize);

        // Store upload session metadata
        $sessionData = [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'context' => $context,
            'user_id' => $userId,
            'metadata' => $metadata,
            'total_chunks' => $totalChunks,
            'uploaded_chunks' => [],
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addMinutes($this->chunkExpiration)->toISOString(),
            'student_id' => $studentId,
        ];

        $this->storeSessionData($uploadId, $sessionData);

        Log::info('Chunked upload session initialized', [
            'upload_id' => $uploadId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'total_chunks' => $totalChunks,
            'context' => $context,
            'user_id' => $userId,
            'student_id' => $studentId,
        ]);

        return [
            'upload_id' => $uploadId,
            'chunk_size' => $this->chunkSize,
            'total_chunks' => $totalChunks,
            'expires_at' => $sessionData['expires_at'],
        ];
    }

    /**
     * Upload a file chunk.
     */
    public function uploadChunk(
        string $uploadId,
        int $chunkIndex,
        UploadedFile $chunkFile,
        UploadActor $actor,
    ): array {
        $sessionData = $this->getSessionData($uploadId);

        if (! $sessionData) {
            throw new InvalidArgumentException('Invalid or expired upload session');
        }

        $this->authorizeActor($sessionData, $actor);

        // Validate chunk index
        if ($chunkIndex < 0 || $chunkIndex >= $sessionData['total_chunks']) {
            throw new InvalidArgumentException('Invalid chunk index');
        }

        // Check if chunk already uploaded
        if (in_array($chunkIndex, $sessionData['uploaded_chunks'])) {
            throw new InvalidArgumentException('Chunk already uploaded');
        }

        // Validate chunk size (except for last chunk)
        $expectedChunkSize = $this->calculateExpectedChunkSize(
            $chunkIndex,
            $sessionData['file_size'],
            $sessionData['total_chunks']
        );

        if ($chunkFile->getSize() !== $expectedChunkSize) {
            throw new InvalidArgumentException(
                "Invalid chunk size. Expected: {$expectedChunkSize}, Got: {$chunkFile->getSize()}"
            );
        }

        // Store chunk
        $chunkPath = $this->getChunkPath($uploadId, $chunkIndex);
        $storedPath = Storage::disk($this->chunkDisk)->putFileAs(
            dirname($chunkPath),
            $chunkFile,
            basename($chunkPath)
        );

        if (! $storedPath) {
            throw new RuntimeException('Failed to store chunk');
        }

        // Update session data
        $sessionData['uploaded_chunks'][] = $chunkIndex;
        sort($sessionData['uploaded_chunks']);
        $this->storeSessionData($uploadId, $sessionData);

        $isComplete = count($sessionData['uploaded_chunks']) === $sessionData['total_chunks'];

        Log::info('Chunk uploaded successfully', [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'chunk_size' => $chunkFile->getSize(),
            'uploaded_chunks' => count($sessionData['uploaded_chunks']),
            'total_chunks' => $sessionData['total_chunks'],
            'is_complete' => $isComplete,
        ]);

        $result = [
            'chunk_index' => $chunkIndex,
            'uploaded_chunks' => count($sessionData['uploaded_chunks']),
            'total_chunks' => $sessionData['total_chunks'],
            'is_complete' => $isComplete,
        ];

        // If all chunks uploaded, assemble the file
        if ($isComplete) {
            $result['upload_record'] = $this->assembleFile($uploadId, $sessionData);
        }

        return $result;
    }

    /**
     * Get upload session status.
     */
    public function getUploadStatus(string $uploadId, UploadActor $actor): array
    {
        $sessionData = $this->getSessionData($uploadId);

        if (! $sessionData) {
            return ['error' => 'Invalid or expired upload session'];
        }

        $this->authorizeActor($sessionData, $actor);

        return [
            'upload_id' => $uploadId,
            'filename' => $sessionData['filename'],
            'file_size' => $sessionData['file_size'],
            'uploaded_chunks' => count($sessionData['uploaded_chunks']),
            'total_chunks' => $sessionData['total_chunks'],
            'progress' => round((count($sessionData['uploaded_chunks']) / $sessionData['total_chunks']) * 100, 2),
            'is_complete' => count($sessionData['uploaded_chunks']) === $sessionData['total_chunks'],
            'expires_at' => $sessionData['expires_at'],
        ];
    }

    /**
     * Cancel chunked upload and cleanup.
     */
    public function cancelUpload(string $uploadId, UploadActor $actor): bool
    {
        $sessionData = $this->getSessionData($uploadId);

        if (! $sessionData) {
            return false;
        }

        $this->authorizeActor($sessionData, $actor);

        // Clean up stored chunks
        $this->cleanupChunks($uploadId, $sessionData['uploaded_chunks']);

        // Remove session data
        $this->removeSessionData($uploadId);

        Log::info('Chunked upload cancelled', [
            'upload_id' => $uploadId,
            'filename' => $sessionData['filename'],
        ]);

        return true;
    }

    /** @param array{user_id: int|null, student_id?: int|null} $sessionData */
    private function authorizeActor(array $sessionData, UploadActor $actor): void
    {
        if (($sessionData['user_id'] ?? null) !== $actor->userId
            || ($sessionData['student_id'] ?? null) !== $actor->studentId) {
            throw new AuthorizationException('Access denied to this upload session');
        }
    }

    /**
     * Assemble chunks into final file.
     */
    protected function assembleFile(string $uploadId, array $sessionData): UploadRecord
    {
        $tempFilePath = $this->createTemporaryFile($uploadId, $sessionData);

        try {
            // Create UploadedFile instance from assembled file
            $uploadedFile = new UploadedFile(
                $tempFilePath,
                $sessionData['filename'],
                null,
                null,
                true // test mode to allow local files
            );

            // Upload using the regular image upload service
            $uploadRecord = $this->imageUploadService->upload(
                $uploadedFile,
                $sessionData['context'],
                $sessionData['user_id'],
                $sessionData['student_id'] ?? null,
                array_merge($sessionData['metadata'], ['chunked_upload' => true])
            );

            // Clean up
            $this->cleanupChunks($uploadId, $sessionData['uploaded_chunks']);
            $this->removeSessionData($uploadId);
            unlink($tempFilePath);

            Log::info('Chunked upload completed successfully', [
                'upload_id' => $uploadId,
                'final_upload_id' => $uploadRecord->id,
                'filename' => $sessionData['filename'],
                'file_size' => $sessionData['file_size'],
            ]);

            return $uploadRecord;

        } catch (\Exception $e) {
            // Clean up on failure
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            throw $e;
        }
    }

    /**
     * Create temporary file by assembling chunks.
     */
    protected function createTemporaryFile(string $uploadId, array $sessionData): string
    {
        $tempFilePath = sys_get_temp_dir().'/'.$uploadId.'_assembled';
        $tempFile = fopen($tempFilePath, 'wb');

        if (! $tempFile) {
            throw new RuntimeException('Unable to create temporary file for assembly');
        }

        try {
            // Assemble chunks in order
            for ($i = 0; $i < $sessionData['total_chunks']; $i++) {
                $chunkPath = $this->getChunkPath($uploadId, $i);

                if (! Storage::disk($this->chunkDisk)->exists($chunkPath)) {
                    throw new RuntimeException("Missing chunk: {$i}");
                }

                $chunkContent = Storage::disk($this->chunkDisk)->get($chunkPath);
                fwrite($tempFile, $chunkContent);
            }

            fclose($tempFile);

            // Verify assembled file size
            $assembledSize = filesize($tempFilePath);
            if ($assembledSize !== $sessionData['file_size']) {
                unlink($tempFilePath);
                throw new RuntimeException(
                    "Assembled file size mismatch. Expected: {$sessionData['file_size']}, Got: {$assembledSize}"
                );
            }

            return $tempFilePath;

        } catch (\Exception $e) {
            fclose($tempFile);
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            throw $e;
        }
    }

    /**
     * Calculate expected chunk size.
     */
    protected function calculateExpectedChunkSize(int $chunkIndex, int $fileSize, int $totalChunks): int
    {
        if ($chunkIndex === $totalChunks - 1) {
            // Last chunk
            return $fileSize - ($chunkIndex * $this->chunkSize);
        }

        return $this->chunkSize;
    }

    /**
     * Get chunk storage path.
     */
    protected function getChunkPath(string $uploadId, int $chunkIndex): string
    {
        return "chunks/{$uploadId}/chunk_{$chunkIndex}";
    }

    /**
     * Store session data in cache.
     */
    protected function storeSessionData(string $uploadId, array $data): void
    {
        $cacheKey = "chunked_upload:{$uploadId}";
        Cache::put($cacheKey, $data, now()->addMinutes($this->chunkExpiration));
    }

    /**
     * Get session data from cache.
     */
    protected function getSessionData(string $uploadId): ?array
    {
        $cacheKey = "chunked_upload:{$uploadId}";

        return Cache::get($cacheKey);
    }

    /**
     * Remove session data from cache.
     */
    protected function removeSessionData(string $uploadId): void
    {
        $cacheKey = "chunked_upload:{$uploadId}";
        Cache::forget($cacheKey);
    }

    /**
     * Clean up stored chunks.
     */
    protected function cleanupChunks(string $uploadId, array $chunkIndexes): void
    {
        foreach ($chunkIndexes as $chunkIndex) {
            $chunkPath = $this->getChunkPath($uploadId, $chunkIndex);

            try {
                Storage::disk($this->chunkDisk)->delete($chunkPath);
            } catch (\Exception $e) {
                Log::warning('Failed to delete chunk', [
                    'upload_id' => $uploadId,
                    'chunk_index' => $chunkIndex,
                    'chunk_path' => $chunkPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Clean up chunk directory if empty
        try {
            $chunkDir = "chunks/{$uploadId}";
            $files = Storage::disk($this->chunkDisk)->files($chunkDir);

            if (empty($files)) {
                Storage::disk($this->chunkDisk)->deleteDirectory($chunkDir);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup chunk directory', [
                'upload_id' => $uploadId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up expired upload sessions.
     */
    public function cleanupExpiredSessions(): array
    {
        $results = [
            'cleaned_sessions' => 0,
            'cleaned_chunks' => 0,
            'errors' => [],
        ];

        try {
            // Get all chunk directories
            $chunkDirectories = Storage::disk($this->chunkDisk)->directories('chunks');

            foreach ($chunkDirectories as $directory) {
                $uploadId = basename($directory);
                $sessionData = $this->getSessionData($uploadId);

                // If session data doesn't exist or is expired, clean up
                if (! $sessionData || now()->isAfter($sessionData['expires_at'])) {
                    try {
                        // Get chunk files before deletion
                        $chunkFiles = Storage::disk($this->chunkDisk)->files($directory);

                        // Delete directory and all chunks
                        Storage::disk($this->chunkDisk)->deleteDirectory($directory);

                        // Remove session data if it exists
                        if ($sessionData) {
                            $this->removeSessionData($uploadId);
                        }

                        $results['cleaned_sessions']++;
                        $results['cleaned_chunks'] += count($chunkFiles);

                        Log::info('Cleaned up expired chunked upload session', [
                            'upload_id' => $uploadId,
                            'chunks_cleaned' => count($chunkFiles),
                        ]);

                    } catch (\Exception $e) {
                        $results['errors'][] = [
                            'upload_id' => $uploadId,
                            'error' => $e->getMessage(),
                        ];

                        Log::error('Failed to cleanup expired session', [
                            'upload_id' => $uploadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'type' => 'general',
                'error' => $e->getMessage(),
            ];

            Log::error('Failed to cleanup expired chunked upload sessions', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Get chunked upload statistics.
     */
    public function getStatistics(): array
    {
        try {
            $chunkDirectories = Storage::disk($this->chunkDisk)->directories('chunks');
            $totalSessions = count($chunkDirectories);
            $totalChunks = 0;
            $activeSessions = 0;
            $expiredSessions = 0;

            foreach ($chunkDirectories as $directory) {
                $uploadId = basename($directory);
                $sessionData = $this->getSessionData($uploadId);
                $chunkFiles = Storage::disk($this->chunkDisk)->files($directory);
                $totalChunks += count($chunkFiles);

                if ($sessionData) {
                    if (now()->isAfter($sessionData['expires_at'])) {
                        $expiredSessions++;
                    } else {
                        $activeSessions++;
                    }
                } else {
                    $expiredSessions++;
                }
            }

            return [
                'total_sessions' => $totalSessions,
                'active_sessions' => $activeSessions,
                'expired_sessions' => $expiredSessions,
                'total_chunks' => $totalChunks,
                'chunk_size' => $this->chunkSize,
                'max_file_size' => $this->maxFileSize,
                'expiration_minutes' => $this->chunkExpiration,
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
