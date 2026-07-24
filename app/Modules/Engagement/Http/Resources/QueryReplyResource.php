<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use App\Shared\Contracts\Upload\FileUploadGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueryReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->resolveAuthor(),
            'message' => $this->message,
            'is_official_answer' => $this->is_official_answer,
            'attachment' => $this->when(
                $this->relationLoaded('uploadRecord') && $this->uploadRecord,
                fn () => $this->formatUploadRecord($this->uploadRecord)
            ),
            'created_at' => $this->created_at,
        ];
    }

    protected function resolveAuthor(): array
    {
        if ($this->author_student_id) {
            return [
                'type' => 'student',
                'id' => $this->author_student_id,
                'name' => $this->whenLoaded('authorStudent', fn () => $this->authorStudent->full_name),
                'student_id' => $this->whenLoaded('authorStudent', fn () => $this->authorStudent->student_id),
            ];
        }

        return [
            'type' => 'staff',
            'id' => $this->author_user_id,
            'name' => $this->whenLoaded('author', fn () => $this->author->name),
        ];
    }

    protected function formatUploadRecord($uploadRecord): array
    {
        $uploadService = app(FileUploadGateway::class);

        $originalName = $uploadRecord->original_name ?? $uploadRecord->filename;

        return [
            'id' => $uploadRecord->id,
            'file_name' => $originalName,
            'filename' => $originalName,
            'size' => $uploadRecord->size,
            'size_bytes' => $uploadRecord->size,
            'mime_type' => $uploadRecord->mime_type,
            'download_url' => $uploadService->urlFor((int) $uploadRecord->id),
        ];
    }
}
