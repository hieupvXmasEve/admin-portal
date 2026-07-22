<?php

declare(strict_types=1);

namespace App\Modules\Upload\Queries;

use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Upload\Support\UploadPlatform;

class ListUploadsQuery
{
    public function __construct(private UploadPlatform $uploads) {}

    public function handle(array $filters, ?User $actor): array
    {
        $query = UploadRecord::query();

        if (! $actor?->hasRole('admin')) {
            $query->where('user_id', $actor?->id);
        }

        if (! empty($filters['context'])) {
            $query->where('context', $filters['context']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('filename', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        $uploads = $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);

        return [
            'data' => $uploads->getCollection()->map(fn (UploadRecord $uploadRecord): array => [
                'id' => $uploadRecord->id,
                'filename' => $uploadRecord->filename,
                'original_name' => $uploadRecord->original_name,
                'mime_type' => $uploadRecord->mime_type,
                'size' => $uploadRecord->size,
                'context' => $uploadRecord->context,
                'url' => $this->uploads->getUrl($uploadRecord),
                'metadata' => $uploadRecord->metadata,
                'created_at' => $uploadRecord->created_at,
            ])->all(),
            'meta' => [
                'current_page' => $uploads->currentPage(),
                'last_page' => $uploads->lastPage(),
                'per_page' => $uploads->perPage(),
                'total' => $uploads->total(),
                'from' => $uploads->firstItem(),
                'to' => $uploads->lastItem(),
            ],
        ];
    }
}
