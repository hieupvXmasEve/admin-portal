<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Models\FormTarget;
use App\Shared\Contracts\Engagement\CourseSurveyTargetReader;
use App\Shared\Contracts\Engagement\DTO\CourseSurveyTarget;

class EloquentCourseSurveyTargetReader implements CourseSurveyTargetReader
{
    public function forCourseOffering(int $courseOfferingId): ?CourseSurveyTarget
    {
        $target = FormTarget::with('form')
            ->where('scope_type', 'course')
            ->where('scope_id', $courseOfferingId)
            ->latest()
            ->first();

        if (! $target) {
            return null;
        }

        $total = $target->assignments()->count();
        $completed = $target->assignments()->where('status', 'completed')->count();

        return new CourseSurveyTarget(
            id: $target->id,
            formId: $target->form_id,
            status: $target->status,
            startAt: $target->start_at->toIso8601String(),
            endAt: $target->end_at?->toIso8601String(),
            formTitle: $target->form?->title,
            formCode: $target->form?->code,
            totalAssignments: $total,
            completedAssignments: $completed,
        );
    }
}
