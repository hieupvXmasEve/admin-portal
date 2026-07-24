<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use Illuminate\Support\Collection;

final class GetStudentHubScoreDetailsQuery
{
    /** @return array<string, mixed> */
    public function handle(int $studentId, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::query()
            ->with(['unit:id,name,code', 'semester:id,name,code'])
            ->findOrFail($courseOfferingId);
        $scores = AssessmentComponentDetailScore::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->with([
                'assessmentComponentDetail.assessmentComponent:id,name,type,weight,description',
                'assessmentComponentDetail:id,assessment_component_id,name,description,due_date,max_points',
            ])
            ->get()
            ->sortBy(static fn (AssessmentComponentDetailScore $score): string => (string) ($score->assessmentComponentDetail?->due_date ?? ''))
            ->groupBy('assessmentComponentDetail.assessment_component_id')
            ->map(function (Collection $componentScores, int|string $componentId): array {
                $firstScore = $componentScores->first();
                $component = $firstScore?->assessmentComponentDetail?->assessmentComponent;

                return [
                    'assessment_component_id' => (int) $componentId,
                    'component_name' => $component?->name ?? 'N/A',
                    'component_type' => $component?->type ?? 'N/A',
                    'component_weight' => $component?->weight ?? 0,
                    'component_description' => $component?->description,
                    'details' => $componentScores->map(static fn (AssessmentComponentDetailScore $score): array => [
                        'id' => $score->id,
                        'name' => $score->assessmentComponentDetail?->name ?? 'N/A',
                        'description' => $score->assessmentComponentDetail?->description,
                        'due_date' => $score->assessmentComponentDetail?->due_date,
                        'max_points' => $score->assessmentComponentDetail?->max_points,
                        'points_earned' => $score->points_earned,
                        'percentage_score' => $score->percentage_score,
                        'letter_grade' => $score->letter_grade,
                        'submitted_at' => $score->submitted_at,
                        'graded_at' => $score->graded_at,
                        'is_late' => $score->is_late,
                        'minutes_late' => $score->minutes_late,
                        'status' => $score->status,
                        'instructor_feedback' => $score->instructor_feedback,
                    ])->values()->all(),
                    'component_average' => $componentScores->avg('percentage_score'),
                    'component_total_points' => $componentScores->sum('points_earned'),
                    'component_max_points' => $componentScores->sum(static fn (AssessmentComponentDetailScore $score): float => (float) ($score->assessmentComponentDetail?->max_points ?? 0)),
                ];
            })
            ->values();

        return [
            'course_info' => [
                'id' => $courseOffering->id,
                'name' => $courseOffering->unit?->name ?? 'N/A',
                'code' => $courseOffering->unit?->code ?? 'N/A',
                'semester' => $courseOffering->semester?->name ?? 'N/A',
            ],
            'assessment_components' => $scores->all(),
            'overall_summary' => [
                'total_components' => $scores->count(),
                'completed_components' => $scores->where('component_average', '>', 0)->count(),
                'overall_average' => $scores->avg('component_average'),
                'weighted_average' => $this->weightedAverage($scores),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $components */
    private function weightedAverage(Collection $components): float
    {
        $totalWeight = (float) $components->sum('component_weight');
        if ($totalWeight === 0.0) {
            return (float) ($components->avg('component_average') ?? 0.0);
        }

        $weightedSum = (float) $components->sum(static fn (array $component): float => (float) ($component['component_average'] ?? 0) * (float) ($component['component_weight'] ?? 0));

        return round($weightedSum / $totalWeight, 2);
    }
}
