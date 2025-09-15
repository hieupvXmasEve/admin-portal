<?php

namespace App\Http\Resources;

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
            'author' => [
                'id' => $this->author_user_id,
                'name' => $this->whenLoaded('author', fn() => $this->author->name),
            ],
            'message' => $this->message,
            'is_official_answer' => $this->is_official_answer,
            'attachment' => $this->when(
                $this->relationLoaded('attachment') && $this->attachment,
                fn() => [
                    'id' => $this->attachment->id,
                    'filename' => $this->attachment->file_name,
                    'size' => $this->attachment->size_bytes,
                ]
            ),
            'created_at' => $this->created_at,
        ];
    }
}
