<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries\Reporting;

use App\Models\AcademicRecord;
use App\Models\CurriculumUnit;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Shared\Contracts\Academic\AcademicReportReader;
use Illuminate\Database\Eloquent\Builder;

final class GetAcademicReportQuery implements AcademicReportReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $semesterId = (int) $filters['semester_id'];
        $isExport = isset($filters['export']);
        $campusId = session('current_campus_id');

        $studentsQuery = Student::query()
            ->where('campus_id', $campusId)
            ->where('status', 'intake_course')
            ->when(
                ! empty($filters['program_id']),
                fn (Builder $query) => $query->where('program_id', $filters['program_id']),
            )
            ->when(! empty($filters['keyword']), function (Builder $query) use ($filters): void {
                $keyword = (string) $filters['keyword'];
                $query->where(function (Builder $nested) use ($keyword): void {
                    $nested
                        ->where('full_name', 'like', "%{$keyword}%")
                        ->orWhere('student_id', 'like', "%{$keyword}%");
                });
            })
            ->whereHas('academicRecords', fn (Builder $query) => $query->where('semester_id', $semesterId));

        $curriculumVersionIds = (clone $studentsQuery)
            ->whereNotNull('curriculum_version_id')
            ->distinct()
            ->pluck('curriculum_version_id');

        $curriculumUnitsByVersion = CurriculumUnit::query()
            ->whereIn('curriculum_version_id', $curriculumVersionIds)
            ->get(['curriculum_version_id', 'unit_id'])
            ->groupBy('curriculum_version_id')
            ->map(static fn ($items): array => $items->pluck('unit_id')->all());

        $curriculumUnitIds = $curriculumUnitsByVersion->flatten()->unique()->values();

        $studentsQuery->with([
            'academicRecords' => function (Builder $query) use ($semesterId, $curriculumUnitIds): void {
                $query
                    ->where('semester_id', $semesterId)
                    ->whereIn('unit_id', $curriculumUnitIds);
            },
            'gpaCalculations' => static fn (Builder $query) => $query->where('is_current', true),
        ]);

        $units = Unit::query()
            ->whereIn('id', $curriculumUnitIds)
            ->whereHas('academicRecords', fn (Builder $query) => $query->where('semester_id', $semesterId))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $gradeDistribution = array_fill_keys([
            'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'F',
        ], 0);

        $allStats = AcademicRecord::query()
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', (clone $studentsQuery)->select('students.id'))
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('students as s2')
                    ->join('curriculum_units as cu', 'cu.curriculum_version_id', '=', 's2.curriculum_version_id')
                    ->whereColumn('s2.id', 'academic_records.student_id')
                    ->whereColumn('cu.unit_id', 'academic_records.unit_id');
            })
            ->select('final_letter_grade')
            ->selectRaw('count(*) as count')
            ->groupBy('final_letter_grade')
            ->pluck('count', 'final_letter_grade')
            ->toArray();

        foreach ($gradeDistribution as $grade => $count) {
            $gradeDistribution[$grade] = (int) ($allStats[$grade] ?? 0);
        }

        $students = $isExport
            ? $studentsQuery->get()
            : $studentsQuery->paginate((int) ($filters['per_page'] ?? 15));
        $items = $isExport ? $students : $students->getCollection();

        $reportData = $items->map(function (Student $student) use ($curriculumUnitsByVersion, $units): array {
            $studentUnitIds = $curriculumUnitsByVersion->get($student->curriculum_version_id, []);
            $row = [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'course_results' => [],
            ];
            $totalAttendance = 0.0;
            $unitCount = 0;

            foreach ($units as $unit) {
                $record = in_array($unit->id, $studentUnitIds, true)
                    ? $student->academicRecords->firstWhere('unit_id', $unit->id)
                    : null;
                $row['course_results'][$unit->id] = [
                    'attendance' => $record?->attendance_percentage,
                    'score' => $record?->final_percentage,
                    'grade' => $record?->final_letter_grade,
                ];

                if ($record?->attendance_percentage !== null) {
                    $totalAttendance += (float) $record->attendance_percentage;
                    $unitCount++;
                }
            }

            $currentGpa = $student->gpaCalculations->first();
            $row['avg_attendance'] = $unitCount > 0 ? round($totalAttendance / $unitCount, 2) : null;
            $row['cumulative_gpa'] = $currentGpa?->cumulative_gpa;

            return $row;
        });

        $stats = [
            'grade_distribution' => $gradeDistribution,
            'total_grades' => array_sum($gradeDistribution),
        ];

        if ($isExport) {
            $semester = Semester::find($semesterId);
            $program = ! empty($filters['program_id']) ? Program::find($filters['program_id']) : null;

            return [
                'units' => $units,
                'data' => $reportData,
                'stats' => $stats,
                'filters' => [
                    'semester' => $semester?->name ?? 'N/A',
                    'program' => $program?->name ?? 'All Programs',
                    'status' => 'In Course',
                    'keyword' => ! empty($filters['keyword']) ? $filters['keyword'] : null,
                ],
            ];
        }

        return [
            'units' => $units,
            'data' => $reportData,
            'pagination' => $students->toArray(),
            'stats' => $stats,
        ];
    }

    public function freshness(): string
    {
        return 'computed_at_request_time';
    }

    public function permissionScope(): string
    {
        return 'view_academic_report_current_campus';
    }

    public function fieldOwnership(): array
    {
        return [
            'units' => AcademicReportReader::class,
            'data' => AcademicReportReader::class,
            'pagination' => AcademicReportReader::class,
            'stats' => AcademicReportReader::class,
            'filters' => AcademicReportReader::class,
        ];
    }
}
