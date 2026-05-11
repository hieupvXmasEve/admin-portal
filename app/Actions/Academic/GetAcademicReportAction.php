<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\CurriculumUnit;
use App\Models\GpaCalculation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class GetAcademicReportAction
{
    /**
     * Execute the academic report logic.
     *
     * @param array $filters
     * @return array
     */
    public function execute(array $filters): array
    {
        $semesterId = $filters['semester_id'];
        $isExport = isset($filters['export']);

        $campusId = session('current_campus_id');

        // 1. Build student query — only intake_course students with records in this semester
        $studentsQuery = Student::query()
            ->where('campus_id', $campusId)
            ->where('status', 'intake_course');

        // Apply filters
        if (!empty($filters['program_id'])) {
            $studentsQuery->where('program_id', $filters['program_id']);
        }
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $studentsQuery->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                    ->orWhere('student_id', 'like', "%{$keyword}%");
            });
        }

        // Filter students who have academic records in this semester
        $studentsQuery->whereHas('academicRecords', function ($query) use ($semesterId) {
            $query->where('semester_id', $semesterId);
        });

        // 2. Map curriculum_version_id => unit_ids[] for the filtered students
        $curriculumVersionIds = (clone $studentsQuery)
            ->whereNotNull('curriculum_version_id')
            ->distinct()
            ->pluck('curriculum_version_id');

        $curriculumUnitsByVersion = CurriculumUnit::query()
            ->whereIn('curriculum_version_id', $curriculumVersionIds)
            ->get(['curriculum_version_id', 'unit_id'])
            ->groupBy('curriculum_version_id')
            ->map(fn ($items) => $items->pluck('unit_id')->all());

        $curriculumUnitIds = $curriculumUnitsByVersion->flatten()->unique()->values();

        // 3. Eager-load academic records constrained to curriculum unit_ids
        $studentsQuery->with([
            'academicRecords' => function ($query) use ($semesterId, $curriculumUnitIds) {
                $query->where('semester_id', $semesterId)
                    ->whereIn('unit_id', $curriculumUnitIds);
            },
            'gpaCalculations' => function ($query) {
                $query->where('is_current', true);
            },
        ]);

        // 4. Units = curriculum units that have academic records in this semester
        $units = Unit::query()
            ->whereIn('id', $curriculumUnitIds)
            ->whereHas('academicRecords', function (Builder $query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // 5. Calculate statistics — only for records on curriculum units of filtered students
        $gradeDistribution = [
            'A+' => 0,
            'A' => 0,
            'A-' => 0,
            'B+' => 0,
            'B' => 0,
            'B-' => 0,
            'C+' => 0,
            'C' => 0,
            'C-' => 0,
            'F' => 0,
        ];

        $allStats = AcademicRecord::query()
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', (clone $studentsQuery)->select('students.id'))
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('students as s2')
                    ->join('curriculum_units as cu', 'cu.curriculum_version_id', '=', 's2.curriculum_version_id')
                    ->whereColumn('s2.id', 'academic_records.student_id')
                    ->whereColumn('cu.unit_id', 'academic_records.unit_id');
            })
            ->select('final_letter_grade', \DB::raw('count(*) as count'))
            ->groupBy('final_letter_grade')
            ->pluck('count', 'final_letter_grade')
            ->toArray();

        foreach ($gradeDistribution as $grade => $count) {
            $gradeDistribution[$grade] = (int) ($allStats[$grade] ?? 0);
        }

        // 6. Fetch paginated/export data
        if ($isExport) {
            $students = $studentsQuery->get();
        } else {
            $students = $studentsQuery->paginate($filters['per_page'] ?? 15);
        }

        // 7. Transform into pivoted format — restrict cells to student's own curriculum
        $items = $isExport ? $students : $students->getCollection();

        $reportData = $items->map(function (Student $student) use ($units, $curriculumUnitsByVersion) {
            $row = [
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'course_results' => [],
            ];

            $studentUnitIds = $curriculumUnitsByVersion->get($student->curriculum_version_id, []);

            $totalAttendance = 0;
            $unitCount = 0;

            foreach ($units as $unit) {
                $inCurriculum = in_array($unit->id, $studentUnitIds, true);
                $record = $inCurriculum
                    ? $student->academicRecords->firstWhere('unit_id', $unit->id)
                    : null;

                $row['course_results'][$unit->id] = [
                    'attendance' => $record ? $record->attendance_percentage : null,
                    'score' => $record ? $record->final_percentage : null,
                    'grade' => $record ? $record->final_letter_grade : null,
                ];

                if ($record && $record->attendance_percentage !== null) {
                    $totalAttendance += (float) $record->attendance_percentage;
                    $unitCount++;
                }
            }

            $currentGpa = $student->gpaCalculations->first();

            $row['avg_attendance'] = $unitCount > 0 ? round($totalAttendance / $unitCount, 2) : null;
            $row['cumulative_gpa'] = $currentGpa ? $currentGpa->cumulative_gpa : null;

            return $row;
        });

        $stats = [
            'grade_distribution' => $gradeDistribution,
            'total_grades' => array_sum($gradeDistribution),
        ];

        if ($isExport) {
            // Build filter context for export
            $semester = Semester::find($semesterId);
            $program = !empty($filters['program_id']) ? Program::find($filters['program_id']) : null;

            $filterContext = [
                'semester' => $semester?->name ?? 'N/A',
                'program' => $program?->name ?? 'All Programs',
                'status' => 'In Course',
                'keyword' => !empty($filters['keyword']) ? $filters['keyword'] : null,
            ];

            return [
                'units' => $units,
                'data' => $reportData,
                'stats' => $stats,
                'filters' => $filterContext,
            ];
        }

        return [
            'units' => $units,
            'data' => $reportData,
            'pagination' => $students->toArray(),
            'stats' => $stats,
        ];
    }
}
