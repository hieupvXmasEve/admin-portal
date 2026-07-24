<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Models\StudentFormAssignment;
use App\Modules\Engagement\Actions\Forms\CheckPortalGateAction;
use App\Shared\Contracts\Engagement\DTO\FormPortalGate;
use App\Shared\Contracts\Engagement\DTO\MandatoryFormAssignment;
use App\Shared\Contracts\Engagement\FormPortalGateReader;

final class EloquentFormPortalGateReader implements FormPortalGateReader
{
    public function __construct(private readonly CheckPortalGateAction $checkPortalGateAction) {}

    public function forStudentId(int $studentId): FormPortalGate
    {
        $result = $this->checkPortalGateAction->execute($studentId);

        return new FormPortalGate(
            blocked: $result['blocked'],
            mandatoryAssignments: $result['mandatory_assignments']
                ->map(fn (StudentFormAssignment $assignment): MandatoryFormAssignment => $this->mapAssignment($assignment))
                ->values()
                ->all(),
        );
    }

    private function mapAssignment(StudentFormAssignment $assignment): MandatoryFormAssignment
    {
        $target = $assignment->formTarget;
        $scope = $target?->scope;
        $targetTitle = match ($target?->scope_type) {
            'course' => $scope?->unit?->name ?? ($scope?->section_code ? "Course: {$scope->section_code}" : 'Course Survey'),
            'semester' => $scope?->name ?? 'Semester Survey',
            'department' => $scope?->name ?? 'Department Survey',
            'global' => 'General Survey',
            default => null,
        };

        return new MandatoryFormAssignment(
            id: (int) $assignment->id,
            formId: $target?->form_id,
            formVersionId: $target?->form_version_id,
            formTitle: ($target?->form?->title ?? '').($targetTitle ? " - {$targetTitle}" : ''),
            originalFormTitle: $target?->form?->title,
            formType: $target?->form?->type,
            targetTitle: $targetTitle,
            targetType: $target?->scope_type,
            targetId: $target?->scope_id,
            dueDate: $target?->end_at,
            status: (string) $assignment->status,
            isMandatory: (bool) ($target?->is_mandatory ?? false),
            courseCode: $target?->scope_type === 'course' ? $scope?->unit?->code : null,
            sectionCode: $target?->scope_type === 'course' ? $scope?->section_code : null,
        );
    }
}
