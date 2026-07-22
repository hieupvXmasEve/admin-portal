<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Upload\Support\UploadPlatform;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $uploadService = app(UploadPlatform::class);

        $originalName = $this->original_name ?? $this->filename ?? $this->file_name;

        return [
            'id' => $this->id,
            'file_name' => $originalName,
            'filename' => $originalName,
            'mime_type' => $this->mime_type,
            'size' => $this->size ?? $this->size_bytes,
            'size_bytes' => $this->size ?? $this->size_bytes,
            'uploaded_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'storage_key' => $this->path ?? $this->storage_key,
            'download_url' => $uploadService->getUrl($this->resource),
        ];
    }
}
