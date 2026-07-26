<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Models\UploadRecord;
use App\Shared\Contracts\Upload\FileUploadGateway;
use App\Shared\Contracts\Upload\StoredUpload;
use Illuminate\Http\UploadedFile;

final class UploadFileGateway implements FileUploadGateway
{
    public function __construct(private readonly UploadPlatform $uploadPlatform) {}

    public function store(
        UploadedFile $file,
        string $context,
        ?int $userId = null,
        ?int $studentId = null,
        array $metadata = [],
    ): StoredUpload {
        return new StoredUpload($this->uploadPlatform->upload($file, $context, $userId, $studentId, $metadata)->id);
    }

    public function urlFor(int $uploadId): string
    {
        return $this->uploadPlatform->getUrl(UploadRecord::query()->findOrFail($uploadId));
    }

    public function availableUrlFor(int $uploadId): ?string
    {
        $upload = UploadRecord::query()->find($uploadId);

        return $upload?->fileExists() ? $this->uploadPlatform->getUrl($upload) : null;
    }

    public function delete(int $uploadId): bool
    {
        $upload = UploadRecord::query()->findOrFail($uploadId);

        return $this->uploadPlatform->delete($upload);
    }

    public function linkToQueryReply(int $uploadId, int $ticketId, int $replyId): void
    {
        $uploadRecord = UploadRecord::query()->findOrFail($uploadId);
        $metadata = $uploadRecord->metadata ?? [];
        $metadata['reply_id'] = $replyId;
        $metadata['ticket_id'] = $ticketId;

        $uploadRecord->update([
            'reply_id' => $replyId,
            'ticket_id' => $ticketId,
            'metadata' => $metadata,
        ]);
    }
}
