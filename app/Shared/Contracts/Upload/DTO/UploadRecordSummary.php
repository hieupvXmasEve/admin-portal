<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload\DTO;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class UploadRecordSummary implements Arrayable
{
    public function __construct(
        public int $id,
        public string $filename,
        public string $originalName,
        public ?string $mimeType,
        public ?int $size,
        public string $url,
        public string $downloadUrl,
        public ?string $uploadedAt,
    ) {}

    /**
     * Matches the fields QueryReplyResource::formatUploadRecord() and
     * AttachmentResource exposed under these same key names before this
     * contract existed, so neither consumer's frontend shape changes.
     * Deliberately omits `storage_key` (AttachmentResource had it; nothing
     * reads it) — everything a live consumer actually uses is here.
     *
     * `filename` and `file_name` are both the display/original name, not
     * the on-disk generated filename — matches the two prior formatters,
     * which both set every name-shaped key to the same original-name value.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->originalName,
            'file_name' => $this->originalName,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'size_bytes' => $this->size,
            'url' => $this->url,
            'download_url' => $this->downloadUrl,
            'uploaded_at' => $this->uploadedAt,
        ];
    }
}
