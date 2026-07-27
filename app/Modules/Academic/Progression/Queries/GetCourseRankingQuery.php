<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\AcademicRecord;

/**
 * Top-10 students per unit for a semester/campus, so the course-ranking page
 * reaches Unit and Student through AcademicRecord relations instead of
 * importing those shared models directly.
 */
class GetCourseRankingQuery
{
    private const TOP_STUDENTS_PER_UNIT = 10;

    /**
     * @return array{
     *   semester_id: int,
     *   courses: list<array{
     *     unit: array{id: int, code: string, name: string},
     *     students: list<array{id: int, student_id: string, full_name: string, final_percentage: float, attendance_percentage: float|null}>,
     *   }>,
     * }
     */
    public function handle(int $semesterId, int $campusId): array
    {
        $records = AcademicRecord::where('semester_id', $semesterId)
            ->where('campus_id', $campusId)
            ->whereNotNull('final_percentage')
            ->whereHas('unit', fn ($query) => $query->where('unit_type', '!=', 'egc'))
            ->with(['unit:id,code,name', 'student:id,student_id,full_name'])
            ->orderBy('final_percentage', 'desc')
            ->orderBy('attendance_percentage', 'desc')
            ->get();

        $courses = $records
            ->groupBy('unit_id')
            ->sortBy(fn ($group) => $group->first()->unit->code)
            ->map(fn ($group) => [
                'unit' => [
                    'id' => $group->first()->unit->id,
                    'code' => $group->first()->unit->code,
                    'name' => $group->first()->unit->name,
                ],
                'students' => $group->take(self::TOP_STUDENTS_PER_UNIT)
                    ->map(fn (AcademicRecord $record): array => [
                        'id' => $record->student->id,
                        'student_id' => $record->student->student_id,
                        'full_name' => $record->student->full_name,
                        'final_percentage' => (float) $record->final_percentage,
                        'attendance_percentage' => $record->attendance_percentage !== null
                            ? (float) $record->attendance_percentage
                            : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return [
            'semester_id' => $semesterId,
            'courses' => $courses,
        ];
    }
}
