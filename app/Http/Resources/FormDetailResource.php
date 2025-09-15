<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormDetailResource extends JsonResource
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

            // All versions with complete structure
            'versions' => $this->when(
                $this->relationLoaded('versions'),
                fn() => $this->versions->map(fn($version) => [
                    'id' => $version->id,
                    'version_no' => $version->version_no,
                    'is_published' => $version->is_published,
                    'effective_from' => $version->effective_from,
                    'effective_to' => $version->effective_to,
                    'created_at' => $version->created_at,

                    // Sections with questions
                    'sections' => $version->relationLoaded('sections')
                        ? $version->sections->map(fn($section) => [
                            'id' => $section->id,
                            'title' => $section->title,
                            'description' => $section->description,
                            'order_index' => $section->order_index,
                            'questions' => $this->mapQuestions($section->questions ?? collect()),
                        ])
                        : null,

                    // Questions without sections
                    'questions' => $version->relationLoaded('questions')
                        ? $this->mapQuestions($version->questions->whereNull('section_id'))
                        : null,
                ])
            ),

            // Current published version with full structure
            'current_version' => $this->when(
                $this->relationLoaded('latestPublishedVersion'),
                fn() => $this->latestPublishedVersion ? [
                    'id' => $this->latestPublishedVersion->id,
                    'version_no' => $this->latestPublishedVersion->version_no,
                    'effective_from' => $this->latestPublishedVersion->effective_from,
                    'effective_to' => $this->latestPublishedVersion->effective_to,

                    // Sections with questions
                    'sections' => $this->latestPublishedVersion->relationLoaded('sections')
                        ? $this->latestPublishedVersion->sections->map(fn($section) => [
                            'id' => $section->id,
                            'title' => $section->title,
                            'description' => $section->description,
                            'order_index' => $section->order_index,
                            'questions' => $this->mapQuestions($section->questions ?? collect()),
                        ])
                        : null,

                    // Questions without sections
                    'questions' => $this->latestPublishedVersion->relationLoaded('questions')
                        ? $this->mapQuestions($this->latestPublishedVersion->questions->whereNull('section_id'))
                        : null,
                ] : null
            ),

            // Targeting configuration
            'targets' => $this->when(
                $this->relationLoaded('targets'),
                fn() => $this->targets->map(fn($target) => [
                    'id' => $target->id,
                    'form_version_id' => $target->form_version_id,
                    'campus_id' => $target->campus_id,
                    'campus_name' => $target->campus?->name,
                    'scope_type' => $target->scope_type,
                    'scope_id' => $target->scope_id,
                    'start_at' => $target->start_at,
                    'end_at' => $target->end_at,
                    'submission_limit_per_user' => $target->submission_limit_per_user,
                    'is_active' => $target->isActive(),
                ])
            ),

            // Visibility configuration
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
                    'role' => [
                        'id' => $visibility->role?->id,
                        'code' => $visibility->role?->code,
                        'name' => $visibility->role?->name,
                    ],
                    'visibility_level' => $visibility->visibility_level,
                    'min_aggregation_threshold' => $visibility->min_aggregation_threshold,
                    'description' => $visibility->getVisibilityDescription(),
                ])
            ),

            // Response statistics
            'statistics' => $this->when(isset($this->statistics), $this->statistics),
            'response_counts' => $this->when(isset($this->response_counts), $this->response_counts),

            // Metadata
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at),
        ];
    }

    /**
     * Map questions with their options.
     */
    protected function mapQuestions($questions)
    {
        return $questions->map(fn($question) => [
            'id' => $question->id,
            'code' => $question->code,
            'text' => $question->text,
            'type' => $question->type,
            'is_required' => $question->is_required,
            'help_text' => $question->help_text,
            'order_index' => $question->order_index,
            'validation_json' => $question->validation_json,
            'visibility_condition_json' => $question->visibility_condition_json,

            // Options for choice questions
            'options' => $question->relationLoaded('options')
                ? $question->options->map(fn($option) => [
                    'id' => $option->id,
                    'value' => $option->value,
                    'label' => $option->label,
                    'order_index' => $option->order_index,
                    'allows_free_text' => $option->allows_free_text,
                ])
                : null,
        ]);
    }
}
