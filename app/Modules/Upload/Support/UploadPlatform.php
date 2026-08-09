<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\UploadRecord;
use Illuminate\Http\UploadedFile;

/**
 * The supported technical boundary for application file handling.
 *
 * Domain callers choose their upload context and metadata; this platform owns
 * validation, storage, retrieval URLs, chunk assembly, and cleanup mechanics.
 */
class UploadPlatform
{
    public function __construct(
        private UploadManager $uploads,
        private ChunkedUploadManager $chunkedUploads,
        private UploadCleanupManager $cleanup,
    ) {}

    public function upload(
        UploadedFile $file,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): UploadRecord {
        return $this->uploads->upload($file, $context, $userId, $studentId, $metadata);
    }

    public function uploadMultiple(
        array $files,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): array {
        return $this->uploads->uploadMultiple($files, $context, $userId, $studentId, $metadata);
    }

    public function uploadWithFilename(
        UploadedFile $file,
        string $context,
        string $filename,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): UploadRecord {
        return $this->uploads->upload($file, $context, $userId, $studentId, $metadata, $filename);
    }

    public function initializeUpload(
        string $filename,
        int $fileSize,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): array {
        return $this->chunkedUploads->initializeUpload(
            $filename,
            $fileSize,
            $context,
            $userId,
            $studentId,
            $metadata,
        );
    }

    public function uploadChunk(string $uploadId, int $chunkIndex, UploadedFile $chunk, UploadActor $actor): array
    {
        return $this->chunkedUploads->uploadChunk($uploadId, $chunkIndex, $chunk, $actor);
    }

    public function getUploadStatus(string $uploadId, UploadActor $actor): array
    {
        return $this->chunkedUploads->getUploadStatus($uploadId, $actor);
    }

    public function cancelUpload(string $uploadId, UploadActor $actor): bool
    {
        return $this->chunkedUploads->cancelUpload($uploadId, $actor);
    }

    public function cleanupExpiredSessions(): array
    {
        return $this->chunkedUploads->cleanupExpiredSessions();
    }

    public function cleanup(array $config = []): array
    {
        return $this->cleanup->cleanup($this, $config);
    }

    public function getStatistics(): array
    {
        return $this->chunkedUploads->getStatistics();
    }

    public function getUrl(UploadRecord $uploadRecord, int $temporaryExpirationMinutes = 60): string
    {
        return $this->uploads->getUrl($uploadRecord, $temporaryExpirationMinutes);
    }

    public function generateUrl(UploadRecord $uploadRecord, array $options = []): string
    {
        return $this->uploads->generateUrlForRecord($uploadRecord, $options);
    }

    public function generatePublicUrl(UploadRecord $uploadRecord, array $options = []): string
    {
        return $this->uploads->generatePublicUrlForRecord($uploadRecord, $options);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->uploads->{$method}(...$arguments);
    }
}
