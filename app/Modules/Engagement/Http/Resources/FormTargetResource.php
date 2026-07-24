<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormTargetResource extends JsonResource
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
            'campus' => [
                'id' => $this->campus_id,
                'name' => $this->whenLoaded('campus', fn () => $this->campus->name),
            ],
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'start_at' => $this->start_at->toISOString(),
            'end_at' => $this->end_at?->toISOString(),
            'submission_limit_per_user' => $this->submission_limit_per_user,
            'is_active' => $this->isActive(),
        ];
    }
}
