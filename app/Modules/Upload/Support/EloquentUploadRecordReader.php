<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\UploadRecord;
use App\Shared\Contracts\Upload\DTO\UploadRecordSummary;
use App\Shared\Contracts\Upload\UploadRecordReader;

class EloquentUploadRecordReader implements UploadRecordReader
{
    public function __construct(private readonly UploadPlatform $uploadPlatform) {}

    public function byReplyIds(array $replyIds): array
    {
        if ($replyIds === []) {
            return [];
        }

        return UploadRecord::query()
            ->whereIn('reply_id', $replyIds)
            ->get()
            ->mapWithKeys(fn (UploadRecord $record) => [(int) $record->reply_id => $this->toSummary($record)])
            ->all();
    }

    public function byResponseIds(array $responseIds): array
    {
        if ($responseIds === []) {
            return [];
        }

        return UploadRecord::query()
            ->whereIn('response_id', $responseIds)
            ->get()
            ->groupBy(fn (UploadRecord $record) => (int) $record->response_id)
            ->map(fn ($records) => $records->map(fn (UploadRecord $record) => $this->toSummary($record))->values()->all())
            ->all();
    }

    private function toSummary(UploadRecord $record): UploadRecordSummary
    {
        return new UploadRecordSummary(
            id: $record->id,
            filename: $record->filename,
            originalName: $record->original_name ?? $record->filename,
            mimeType: $record->mime_type,
            size: $record->size,
            url: $record->url,
            downloadUrl: $this->uploadPlatform->getUrl($record),
            uploadedAt: $record->created_at?->format('Y-m-d H:i:s'),
        );
    }
}
