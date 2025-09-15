<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Simplify response for Student Portal form list requests.
        // If the request path targets student forms, return a minimal payload
        // and implicitly skip any submission count checks (e.g., when type=query).
        $isStudentFormsRequest = str_contains($request->path(), 'api/v1/student/forms');

        if ($isStudentFormsRequest) {
            return [
                'id' => $this->id,
                'code' => $this->code,
                'type' => $this->type,
                'title' => $this->title,
                'description' => $this->description,
                'status' => $this->status,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->created_by,

            // Creator information
            'creator' => $this->when(
                $this->relationLoaded('creator'),
                fn() => [
                    'id' => $this->creator?->id,
                    'name' => $this->creator?->name,
                    'email' => $this->creator?->email,
                ]
            ),

            // Version information
            'current_version' => new FormVersionResource($this->whenLoaded('latestPublishedVersion')),
            'versions' => FormVersionResource::collection($this->whenLoaded('versions')),
            'latest_version' => $this->when(
                $this->relationLoaded('versions'),
                fn() => $this->versions->sortByDesc('version_no')->first()?->version_no ?? 0
            ),

            // Targeting and visibility
            'targets' => FormTargetResource::collection($this->whenLoaded('targets')),
            'visibility_roles' => $this->when(
                $this->relationLoaded('visibilityRoles'),
                fn() => $this->visibilityRoles->map(fn($role) => [
                    'id' => $role->id,
                    'code' => $role->code,
                    'name' => $role->name,
                ])
            ),
            'result_visibility' => $this->when(
                $this->relationLoaded('resultVisibility'),
                fn() => $this->resultVisibility->map(fn($visibility) => [
                    'id' => $visibility->id,
                    'role_id' => $visibility->role_id,
                    'role_name' => $visibility->role?->name,
                    'visibility_level' => $visibility->visibility_level,
                    'min_aggregation_threshold' => $visibility->min_aggregation_threshold,
                ])
            ),

            // Response statistics
            'statistics' => $this->when(isset($this->statistics), $this->statistics),
            'response_counts' => $this->when(isset($this->response_counts), $this->response_counts),

            // Student-specific fields
            'can_submit' => $this->when(isset($this->can_submit), $this->can_submit),
            'submission_count' => $this->when(isset($this->submission_count), $this->submission_count),
            'is_available' => $this->when(isset($this->is_available), $this->is_available),

            // Admin-specific fields
            'has_responses' => $this->when(
                isset($this->responses_count),
                $this->responses_count > 0
            ),
            'active_targets_count' => $this->when(
                $this->relationLoaded('targets'),
                fn() => $this->targets->filter(fn($target) => $target->isActive())->count()
            ),

            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }
}
