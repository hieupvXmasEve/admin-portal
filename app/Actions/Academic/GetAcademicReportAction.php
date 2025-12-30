<?php

declare(strict_types=1);

namespace App\Actions\Academic;

use App\Models\AcademicRecord;
use App\Models\GpaCalculation;
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

        // 2. Build student query
        $studentsQuery = Student::query()
            ->with([
                'academicRecords' => function ($query) use ($semesterId) {
                    $query->where('semester_id', $semesterId);
                },
                'gpaCalculations' => function ($query) {
                    $query->where('is_current', true);
                }
            ]);

        // Apply filters
        if (!empty($filters['campus_id'])) {
            $studentsQuery->where('campus_id', $filters['campus_id']);
        }
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

        // 3. Fetch data
        if ($isExport) {
            $students = $studentsQuery->get();
        } else {
            $students = $studentsQuery->paginate($filters['per_page'] ?? 15);
        }

        // 4. Transform into pivoted format
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

        if ($isExport) {
            return [
                'units' => $units,
                'data' => $reportData,
            ];
        }

        return [
            'units' => $units,
            'data' => $reportData,
            'pagination' => $students->toArray(),
        ];
    }
}
