<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Services\CourseCompletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLecturerGradebookScoresAction
{
    public function __construct(
        private readonly CourseCompletionService $courseCompletionService,
    ) {}

    /**
     * @param  array<int, array{detail_id:int, student_id:int, points_earned:int|float|string}>  $scores
     * @return array<string, mixed>
     */
    public function handle(CourseOffering $courseOffering, array $scores, int $lecturerId): array
    {
        $validatedScores = $this->validateScores($courseOffering, $scores);

        $created = [];
        $updated = [];
        $aggregated = false;

        DB::transaction(function () use ($courseOffering, $validatedScores, $lecturerId, &$created, &$updated, &$aggregated): void {
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
                    'letter_grade' => AcademicRecord::calculateLetterGrade($percentageScore),
                    'status' => 'graded',
                    'graded_by_lecture_id' => $lecturerId,
                    'graded_at' => now(),
                    'last_modified_by_lecture_id' => $lecturerId,
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

            if ($this->shouldAggregate($courseOffering)) {
                $this->courseCompletionService->aggregateManualGrades($courseOffering);
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

    /**
     * @param  array<int, array{detail_id:int, student_id:int, points_earned:int|float|string}>  $scores
     * @return array<int, array{detail:AssessmentComponentDetail, student_id:int, points_earned:float, max_points:float}>
     */
    private function validateScores(CourseOffering $courseOffering, array $scores): array
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if ($syllabus === null) {
            throw ValidationException::withMessages([
                'course_offering' => ['Course offering does not have a syllabus template.'],
            ]);
        }

        $detailIds = collect($scores)->pluck('detail_id')->unique()->values();
        $studentIds = collect($scores)->pluck('student_id')->unique()->values();

        $details = AssessmentComponentDetail::query()
            ->with('assessmentComponent')
            ->whereIn('id', $detailIds)
            ->get()
            ->keyBy('id');

        $enrolledStudentIds = CourseRegistration::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->flip();

        $errors = [];
        $seenCells = [];
        $validatedScores = [];

        foreach ($scores as $index => $scoreData) {
            $detailId = (int) $scoreData['detail_id'];
            $studentId = (int) $scoreData['student_id'];
            $pointsEarned = (float) $scoreData['points_earned'];
            $cellKey = "{$detailId}:{$studentId}";

            if (isset($seenCells[$cellKey])) {
                $errors["scores.{$index}.detail_id"][] = 'Duplicate score cell in request.';

                continue;
            }
            $seenCells[$cellKey] = true;

            $detail = $details->get($detailId);
            if ($detail === null || $detail->assessmentComponent === null) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail was not found.';

                continue;
            }

            if ((int) $detail->assessmentComponent->syllabus_template_id !== (int) $syllabus->id) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail does not belong to this course offering syllabus.';

                continue;
            }

            if (! $enrolledStudentIds->has($studentId)) {
                $errors["scores.{$index}.student_id"][] = 'Student is not enrolled in this course offering.';

                continue;
            }

            $maxPoints = $this->effectiveMaxPoints($detail->max_points);
            if ($maxPoints <= 0) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail is not gradable because max points is zero.';

                continue;
            }

            if ($pointsEarned > $maxPoints) {
                $errors["scores.{$index}.points_earned"][] = "Points earned may not be greater than {$maxPoints}.";

                continue;
            }

            $validatedScores[] = [
                'detail' => $detail,
                'student_id' => $studentId,
                'points_earned' => $pointsEarned,
                'max_points' => $maxPoints,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $validatedScores;
    }

    private function shouldAggregate(CourseOffering $courseOffering): bool
    {
        return ! $courseOffering->is_canvas_synced
            && in_array($courseOffering->course_status, ['not_started', 'in_progress'], true);
    }

    private function effectiveMaxPoints(mixed $maxPoints): float
    {
        if ($maxPoints === null || $maxPoints === '') {
            return 100.0;
        }

        return (float) $maxPoints;
    }
}
