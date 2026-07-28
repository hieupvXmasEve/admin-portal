<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Services\CourseCompletionService;
use App\Shared\Support\Academic\CourseGradeScale;
use Illuminate\Support\Facades\DB;

final class SaveLecturerGradebookScoresAction
{
    /**
     * @param  array{
     *   course_offering: CourseOffering,
     *   scores: array<int, array{detail:AssessmentComponentDetail, student_id:int, points_earned:float, max_points:float}>,
     *   lecturer_id: int,
     * }  $data
     * @return array<string, mixed>
     */
    public static function run(array $data): array
    {
        $courseOffering = $data['course_offering'];
        $validatedScores = $data['scores'];

        $created = [];
        $updated = [];
        $aggregated = false;

        DB::transaction(function () use ($courseOffering, $validatedScores, $data, &$created, &$updated, &$aggregated): void {
            foreach ($validatedScores as $scoreData) {
                $score = AssessmentComponentDetailScore::firstOrNew([
                    'assessment_component_detail_id' => $scoreData['detail']->id,
                    'student_id' => $scoreData['student_id'],
                    'course_offering_id' => $courseOffering->id,
                ]);

                $isNew = ! $score->exists;
                $percentageScore = round(((float) $scoreData['points_earned'] / $scoreData['max_points']) * 100, 2);

                $score->fill([
                    'points_earned' => $scoreData['points_earned'],
                    'percentage_score' => $percentageScore,
                    'letter_grade' => CourseGradeScale::letterGrade($percentageScore),
                    'status' => 'graded',
                    'graded_by_lecture_id' => $data['lecturer_id'],
                    'graded_at' => now(),
                    'last_modified_by_lecture_id' => $data['lecturer_id'],
                    'last_modified_at' => now(),
                    'score_excluded' => false,
                ]);

                $score->save();

                $entry = [
                    'student_id' => $score->student_id,
                    'detail_id' => $score->assessment_component_detail_id,
                    'score_id' => $score->id,
                ];

                if ($isNew) {
                    $created[] = $entry;
                } else {
                    $updated[] = $entry;
                }
            }

            if (self::shouldAggregate($courseOffering)) {
                app(CourseCompletionService::class)->aggregateManualGrades($courseOffering);
                $aggregated = true;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'total_saved' => count($created) + count($updated),
            'aggregated' => $aggregated,
        ];
    }

    private static function shouldAggregate(CourseOffering $courseOffering): bool
    {
        return ! $courseOffering->is_canvas_synced
            && in_array($courseOffering->course_status, ['not_started', 'in_progress'], true);
    }
}
