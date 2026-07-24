<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AssessmentComponentDetailScore;

final class GetStudentHubCourseScoresQuery
{
    /** @return array{data: list<array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, has_more: bool}} */
    public function handle(int $studentId, int $courseOfferingId, int $offset = 0, int $limit = 20): array
    {
        $baseQuery = AssessmentComponentDetailScore::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId);
        $totalCount = (clone $baseQuery)->count();
        $scores = $baseQuery
            ->with([
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->orderByDesc('graded_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'data' => $scores->map(static fn (AssessmentComponentDetailScore $score): array => [
                'id' => $score->id,
                'assessment_name' => $score->assessmentComponentDetail?->name ?? 'N/A',
                'assessment_type' => $score->assessmentComponentDetail?->assessmentComponent?->type ?? 'N/A',
                'due_date' => $score->assessmentComponentDetail?->due_date,
                'max_points' => $score->assessmentComponentDetail?->max_points,
                'points_earned' => $score->points_earned,
                'percentage_score' => $score->percentage_score,
                'letter_grade' => $score->letter_grade,
                'gpa_points' => $score->gpa_points,
                'submitted_at' => $score->submitted_at,
                'graded_at' => $score->graded_at,
                'is_late' => $score->is_late,
                'status' => $score->status,
            ])->all(),
            'pagination' => [
                'total' => $totalCount,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalCount,
            ],
        ];
    }
}
