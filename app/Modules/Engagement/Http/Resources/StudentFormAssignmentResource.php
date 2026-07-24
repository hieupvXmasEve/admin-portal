<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentFormAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $target = $this->formTarget;
        $scope = $target?->scope;

        $targetTitle = match ($target?->scope_type) {
            'course' => $scope?->unit?->name ?? ($scope?->section_code ? "Course: {$scope->section_code}" : 'Course Survey'),
            'semester' => $scope?->name ?? 'Semester Survey',
            'department' => $scope?->name ?? 'Department Survey',
            'global' => 'General Survey',
            default => null,
        };

        return [
            'id' => $this->id,
            'form_id' => $target?->form_id,
            'form_version_id' => $target?->form_version_id,
            'form_title' => $target?->form?->title.($targetTitle ? " - $targetTitle" : ''),
            'original_form_title' => $target?->form?->title,
            'form_type' => $target?->form?->type,
            'target_title' => $targetTitle,
            'target_type' => $target?->scope_type,
            'target_id' => $target?->scope_id,
            'due_date' => $target?->end_at,
            'status' => $this->status,
            'is_mandatory' => $target?->is_mandatory ?? false,
            // Additional info for course surveys
            'course_code' => $target?->scope_type === 'course' ? $scope?->unit?->code : null,
            'section_code' => $target?->scope_type === 'course' ? $scope?->section_code : null,
        ];
    }
}
