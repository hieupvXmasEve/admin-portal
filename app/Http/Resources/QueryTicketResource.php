<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueryTicketResource extends JsonResource
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
            'topic' => $this->when(
                $this->relationLoaded('topic') && $this->topic,
                fn() => [
                    'id' => $this->topic->id,
                    'title' => $this->topic->title,
                ]
            ),
            'custom_topic_text' => $this->custom_topic_text,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->when(
                $this->relationLoaded('assignedTo') && $this->assignedTo,
                fn() => [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'closed_at' => $this->closed_at,
            'replies' => QueryReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
