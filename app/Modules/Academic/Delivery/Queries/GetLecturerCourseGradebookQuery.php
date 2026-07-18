<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Modules\Academic\Support\Grading\Presenters\GradeDisplayPresenter;
use Illuminate\Support\Collection;

final class GetLecturerCourseGradebookQuery
{
    public function __construct(
        private readonly GradeDisplayPresenter $gradeDisplayPresenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing(['unit', 'semester', 'syllabusTemplate']);

        $syllabus = $courseOffering->syllabusTemplate;
        if ($syllabus === null) {
            return $this->emptyPayload($courseOffering);
        }

        $components = AssessmentComponent::query()
            ->where('syllabus_template_id', $syllabus->id)
            ->with(['details' => fn ($query) => $query->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = $this->buildItems($components);
        $students = $courseOffering->courseRegistrations()
            ->with('student')
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('student_id')
            ->values();

        $detailIds = collect($items)->pluck('detail_id')->all();
        $studentIds = $students->pluck('id')->all();

        $scores = AssessmentComponentDetailScore::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('assessment_component_detail_id', $detailIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $studentScores) => $studentScores->keyBy('assessment_component_detail_id'));

        $academicRecords = AcademicRecord::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $items = $this->applyItemStatistics($items, $students, $scores);
        $studentRows = $this->buildStudentRows($students, $items, $scores, $academicRecords);

        return [
            'course_offering' => [
                'id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'section_code' => $courseOffering->section_code,
                'semester' => $courseOffering->semester ? [
                    'id' => $courseOffering->semester->id,
                    'name' => $courseOffering->semester->name,
                ] : null,
            ],
            'summary' => $this->buildSummary($items, $studentRows),
            'items' => $items,
            'students' => $studentRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(CourseOffering $courseOffering): array
    {
        return [
            'course_offering' => [
                'id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'section_code' => $courseOffering->section_code,
                'semester' => null,
            ],
            'summary' => [
                'students' => 0,
                'items' => 0,
                'graded_cells' => 0,
                'pending_cells' => 0,
                'missing_cells' => 0,
                'class_average' => null,
            ],
            'items' => [],
            'students' => [],
        ];
    }

    /**
     * @param  Collection<int, AssessmentComponent>  $components
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(Collection $components): array
    {
        $items = [];

        foreach ($components as $component) {
            if ($component->details->isEmpty()) {
                $component->ensureHasDetails();
                $component->load('details');
            }

            foreach ($component->details as $detail) {
                $items[] = [
                    'detail_id' => $detail->id,
                    'component_id' => $component->id,
                    'component_code' => $component->code,
                    'component_name' => $component->name,
                    'detail_name' => $detail->name,
                    'type' => $component->type,
                    'weight' => (float) $detail->weight,
                    'component_weight' => (float) $component->weight,
                    'max_points' => $this->effectiveMaxPoints($detail->max_points),
                    'editable' => $this->effectiveMaxPoints($detail->max_points) > 0,
                    'graded_count' => 0,
                    'total_students' => 0,
                    'pending_count' => 0,
                    'missing_count' => 0,
                    'average_percentage' => null,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Collection<int, AssessmentComponentDetailScore>>  $scores
     * @return array<int, array<string, mixed>>
     */
    private function applyItemStatistics(array $items, Collection $students, Collection $scores): array
    {
        return collect($items)
            ->map(function (array $item) use ($students, $scores) {
                $percentages = [];
                $gradedCount = 0;

                foreach ($students as $student) {
                    $score = $scores->get($student->id)?->get($item['detail_id']);
                    if ($score !== null && $score->points_earned !== null) {
                        $gradedCount++;
                    }

                    if ($score !== null && $score->percentage_score !== null) {
                        $percentages[] = (float) $score->percentage_score;
                    }
                }

                $totalStudents = $students->count();

                return array_merge($item, [
                    'graded_count' => $gradedCount,
                    'total_students' => $totalStudents,
                    'pending_count' => max(0, $totalStudents - $gradedCount),
                    'missing_count' => max(0, $totalStudents - $gradedCount),
                    'average_percentage' => $percentages === []
                        ? null
                        : round(array_sum($percentages) / count($percentages), 2),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Collection<int, AssessmentComponentDetailScore>>  $scores
     * @param  Collection<int, AcademicRecord>  $academicRecords
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentRows(
        Collection $students,
        array $items,
        Collection $scores,
        Collection $academicRecords,
    ): array {
        return $students
            ->map(function ($student) use ($items, $scores, $academicRecords) {
                $record = $academicRecords->get($student->id);

                return [
                    'student_id' => $student->id,
                    'student_code' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'cells' => collect($items)
                        ->map(function (array $item) use ($student, $scores) {
                            $score = $scores->get($student->id)?->get($item['detail_id']);

                            return [
                                'detail_id' => $item['detail_id'],
                                'score_id' => $score?->id,
                                'points_earned' => $score?->points_earned !== null ? (float) $score->points_earned : null,
                                'percentage_score' => $score?->percentage_score !== null ? (float) $score->percentage_score : null,
                                'status' => $score?->status ?? 'missing',
                                'score_status' => $score?->score_status ?? null,
                                'editable' => (bool) $item['editable'],
                            ];
                        })
                        ->values()
                        ->all(),
                    'final_percentage' => $record?->final_percentage !== null ? (float) $record->final_percentage : null,
                    'final_letter_grade' => $record?->final_letter_grade,
                    'grade_display' => $record !== null && is_array($record->grade_breakdown) && $record->grade_breakdown !== []
                        ? $this->gradeDisplayPresenter->present($record)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $studentRows
     * @return array<string, mixed>
     */
    private function buildSummary(array $items, array $studentRows): array
    {
        $gradedCells = collect($items)->sum('graded_count');
        $totalCells = count($items) * count($studentRows);
        $finalPercentages = collect($studentRows)
            ->pluck('final_percentage')
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->values();

        return [
            'students' => count($studentRows),
            'items' => count($items),
            'graded_cells' => $gradedCells,
            'pending_cells' => max(0, $totalCells - $gradedCells),
            'missing_cells' => max(0, $totalCells - $gradedCells),
            'class_average' => $finalPercentages->isEmpty() ? null : round($finalPercentages->average(), 2),
        ];
    }

    private function effectiveMaxPoints(mixed $maxPoints): float
    {
        if ($maxPoints === null || $maxPoints === '') {
            return 100.0;
        }

        return (float) $maxPoints;
    }
}
