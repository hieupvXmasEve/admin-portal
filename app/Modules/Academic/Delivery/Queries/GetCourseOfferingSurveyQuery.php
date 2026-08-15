<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Shared\Contracts\Engagement\CourseSurveyTargetReader;

/**
 * Return survey data for a course offering's deferred prop.
 *
 * Returns null when no survey has been created for the course.
 *
 * @return array{
 *   surveyTarget: array{id: int, form_id: int, status: string, start_at: string, end_at: string|null}|null,
 *   completionStats: array{total: int, completed: int, pending: int}|null,
 *   formVersion: array{id: int, title: string, code: string}|null,
 * }|null
 */
final class GetCourseOfferingSurveyQuery
{
    public function __construct(private readonly CourseSurveyTargetReader $surveyTargets) {}

    public function handle(CourseOffering $courseOffering): ?array
    {
        $target = $this->surveyTargets->forCourseOffering($courseOffering->id);

        if (! $target) {
            return null;
        }

        return [
            'surveyTarget' => [
                'id' => $target->id,
                'form_id' => $target->formId,
                'status' => $target->status,
                'start_at' => $target->startAt,
                'end_at' => $target->endAt,
            ],
            'completionStats' => [
                'total' => $target->totalAssignments,
                'completed' => $target->completedAssignments,
                'pending' => $target->totalAssignments - $target->completedAssignments,
            ],
            'formVersion' => $target->formTitle !== null ? [
                'id' => $target->formId,
                'title' => $target->formTitle,
                'code' => $target->formCode,
            ] : null,
        ];
    }
}
