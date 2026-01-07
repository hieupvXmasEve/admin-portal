<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
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

        // 1. Get all units associated with this semester (to define columns)
        $units = Unit::whereHas('academicRecords', function (Builder $query) use ($semesterId) {
            $query->where('semester_id', $semesterId);
        })->orderBy('code')->get(['id', 'code', 'name']);
        $campusId = session('current_campus_id');
        // 2. Build student query
        $studentsQuery = Student::query()
            ->where('campus_id', $campusId)
            ->with([
                'academicRecords' => function ($query) use ($semesterId) {
                    $query->where('semester_id', $semesterId);
                },
                'gpaCalculations' => function ($query) {
                    $query->where('is_current', true);
                }
            ]);

        // Apply filters
        if (!empty($filters['program_id'])) {
            $studentsQuery->where('program_id', $filters['program_id']);
        }
        if (!empty($filters['status'])) {
            $studentsQuery->where('status', $filters['status']);
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

        // 3. Calculate statistics for ALL filtered records (ignoring pagination)
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
            ->select('final_letter_grade', \DB::raw('count(*) as count'))
            ->groupBy('final_letter_grade')
            ->pluck('count', 'final_letter_grade')
            ->toArray();

        foreach ($gradeDistribution as $grade => $count) {
            $gradeDistribution[$grade] = (int) ($allStats[$grade] ?? 0);
        }

        // 4. Fetch paginated/export data
        if ($isExport) {
            $students = $studentsQuery->get();
        } else {
            $students = $studentsQuery->paginate($filters['per_page'] ?? 15);
        }

        // 5. Transform into pivoted format
        $items = $isExport ? $students : $students->getCollection();

        $reportData = $items->map(function (Student $student) use ($units) {
            $row = [
                'full_name' => $student->full_name,
                'student_id' => $student->student_id,
                'course_results' => [],
            ];

            $totalAttendance = 0;
            $unitCount = 0;

            foreach ($units as $unit) {
                $record = $student->academicRecords->firstWhere('unit_id', $unit->id);

                $row['course_results'][$unit->id] = [
                    'attendance' => $record ? $record->attendance_percentage : null,
                    'score' => $record ? $record->final_percentage : null,
                    'grade' => $record ? $record->final_letter_grade : null,
                ];

                if ($record) {
                    if ($record->attendance_percentage !== null) {
                        $totalAttendance += (float) $record->attendance_percentage;
                        $unitCount++;
                    }
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
                'status' => !empty($filters['status']) ? ucfirst($filters['status']) : 'All Statuses',
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
