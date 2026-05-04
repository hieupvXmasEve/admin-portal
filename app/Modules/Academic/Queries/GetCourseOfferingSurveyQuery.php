<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Models\FormTarget;

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
class GetCourseOfferingSurveyQuery
{
    public static function handle(CourseOffering $courseOffering): ?array
    {
        /** @var FormTarget|null $target */
        $target = FormTarget::with(['form', 'assignments'])
            ->where('scope_type', 'course')
            ->where('scope_id', $courseOffering->id)
            ->latest()
            ->first();

        if (! $target) {
            return null;
        }

        $form = $target->form;

        // Completion stats from student assignments
        $total = $target->assignments()->count();
        $completed = $target->assignments()->where('status', 'completed')->count();
        $pending = $total - $completed;

        return [
            'surveyTarget' => [
                'id' => $target->id,
                'form_id' => $target->form_id,
                'status' => $target->status,
                'start_at' => $target->start_at->toIso8601String(),
                'end_at' => $target->end_at?->toIso8601String(),
            ],
            'completionStats' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => $pending,
            ],
            'formVersion' => $form ? [
                'id' => $form->id,
                'title' => $form->title,
                'code' => $form->code,
            ] : null,
        ];
    }
}
