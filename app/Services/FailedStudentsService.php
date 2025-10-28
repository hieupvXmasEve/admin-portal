<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FailedStudentsService
{
    /**
     * Get paginated list of failed students with filters
     */
    public function getFailedStudents(array $filters): LengthAwarePaginator
    {
        $campusId = session('current_campus_id');
        $perPage = $filters['per_page'] ?? 15;
        $search = $filters['search'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;
        $programId = $filters['program_id'] ?? null;
        $unitId = $filters['unit_id'] ?? null;
        $attemptNumber = $filters['attempt_number'] ?? null;
        $sortBy = $filters['sort'] ?? 'student_id';
        $direction = $filters['direction'] ?? 'asc';

        $query = AcademicRecord::query()
            ->with([
                'student:id,student_id,full_name,email',
                'program:id,name,code',
                'campus:id,name,code',
                'unit:id,code,name,credit_points',
                'courseOffering:id,section_code,lecture_id',
                'courseOffering.lecture:id,first_name,last_name,title',
                'semester:id,name,code',
            ])
            ->where('academic_records.campus_id', $campusId)
            ->where(function ($q) {
                $q->where('academic_records.completion_status', 'failed')
                    ->orWhere('academic_records.final_letter_grade', 'F');
            });

        // Apply filters
        if ($semesterId) {
            $query->where('academic_records.semester_id', $semesterId);
        }

        if ($programId) {
            $query->where('academic_records.program_id', $programId);
        }

        if ($unitId) {
            $query->where('academic_records.unit_id', $unitId);
        }

        if ($attemptNumber) {
            if ($attemptNumber === '3+') {
                $query->where('academic_records.attempt_number', '>=', 3);
            } else {
                $query->where('academic_records.attempt_number', (int) $attemptNumber);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        // Sorting
        $sortColumn = match ($sortBy) {
            'student_name' => 'students.full_name',
            'unit_code' => 'units.code',
            'final_percentage' => 'academic_records.final_percentage',
            'attendance_percentage' => 'academic_records.attendance_percentage',
            'attempt_number' => 'academic_records.attempt_number',
            default => 'students.student_id',
        };

        $query->leftJoin('students', 'academic_records.student_id', '=', 'students.id')
            ->leftJoin('units', 'academic_records.unit_id', '=', 'units.id')
            ->select('academic_records.*')
            ->orderBy($sortColumn, $direction);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get summary statistics for failed students
     */
    public function getSummaryStatistics(int $semesterId, ?int $programId = null): array
    {
        $campusId = session('current_campus_id');

        $query = AcademicRecord::query()
            ->where('academic_records.campus_id', $campusId)
            ->where('academic_records.semester_id', $semesterId)
            ->where(function ($q) {
                $q->where('academic_records.completion_status', 'failed')
                    ->orWhere('academic_records.final_letter_grade', 'F');
            });

        if ($programId) {
            $query->where('academic_records.program_id', $programId);
        }

        $totalFailedStudents = $query->distinct('student_id')->count('student_id');
        $totalFailedCourses = $query->distinct('course_offering_id')->count('course_offering_id');
        $averageAttendance = round((float) ($query->avg('attendance_percentage') ?? 0), 2);

        // Most failed units (top 3)
        $mostFailedUnits = AcademicRecord::query()
            ->select('units.code', 'units.name', DB::raw('COUNT(*) as fail_count'))
            ->join('units', 'academic_records.unit_id', '=', 'units.id')
            ->where('academic_records.campus_id', $campusId)
            ->where('academic_records.semester_id', $semesterId)
            ->where(function ($q) {
                $q->where('academic_records.completion_status', 'failed')
                    ->orWhere('academic_records.final_letter_grade', 'F');
            })
            ->when($programId, function ($q) use ($programId) {
                $q->where('academic_records.program_id', $programId);
            })
            ->groupBy('units.id', 'units.code', 'units.name')
            ->orderByDesc('fail_count')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return [
                    'unit_code' => $item->code,
                    'unit_name' => $item->name,
                    'count' => (int) $item->fail_count,
                ];
            })
            ->toArray();

        // Retake eligible count (students on 1st or 2nd attempt)
        $retakeEligibleCount = (clone $query)
            ->where('academic_records.attempt_number', '<=', 2)
            ->count();

        // By attempt distribution
        $attemptDistribution = (clone $query)
            ->select('attempt_number', DB::raw('COUNT(*) as count'))
            ->groupBy('attempt_number')
            ->get()
            ->mapWithKeys(function ($item) {
                $attemptLabel = $item->attempt_number >= 3 ? '3+' : "{$item->attempt_number}";

                return [$attemptLabel => (int) $item->count];
            })
            ->toArray();

        return [
            'total_failed_students' => $totalFailedStudents,
            'total_failed_courses' => $totalFailedCourses,
            'most_failed_units' => $mostFailedUnits,
            'average_attendance_of_failed' => $averageAttendance,
            'retake_eligible_count' => $retakeEligibleCount,
            'by_attempt_distribution' => $attemptDistribution,
        ];
    }

    /**
     * Get fail reason distribution
     */
    public function getFailReasonDistribution(int $semesterId, ?int $programId = null): array
    {
        $campusId = session('current_campus_id');

        $query = AcademicRecord::query()
            ->where('academic_records.campus_id', $campusId)
            ->where('academic_records.semester_id', $semesterId)
            ->where(function ($q) {
                $q->where('academic_records.completion_status', 'failed')
                    ->orWhere('academic_records.final_letter_grade', 'F');
            });

        if ($programId) {
            $query->where('academic_records.program_id', $programId);
        }

        $lowGradeOnly = (clone $query)
            ->where('academic_records.final_percentage', '<', 50)
            ->where('academic_records.attendance_percentage', '>=', 80)
            ->count();

        $poorAttendanceOnly = (clone $query)
            ->where('academic_records.final_percentage', '>=', 50)
            ->where('academic_records.attendance_percentage', '<', 80)
            ->count();

        $both = (clone $query)
            ->where('academic_records.final_percentage', '<', 50)
            ->where('academic_records.attendance_percentage', '<', 80)
            ->count();

        return [
            'low_grade' => $lowGradeOnly,
            'poor_attendance' => $poorAttendanceOnly,
            'both' => $both,
        ];
    }

    /**
     * Get failed students distribution by unit (for bar chart)
     */
    public function getFailedUnitDistribution(int $semesterId, ?int $programId = null): array
    {
        $campusId = session('current_campus_id');

        return AcademicRecord::query()
            ->select('units.code', 'units.name', DB::raw('COUNT(*) as fail_count'))
            ->join('units', 'academic_records.unit_id', '=', 'units.id')
            ->where('academic_records.campus_id', $campusId)
            ->where('academic_records.semester_id', $semesterId)
            ->where(function ($q) {
                $q->where('academic_records.completion_status', 'failed')
                    ->orWhere('academic_records.final_letter_grade', 'F');
            })
            ->when($programId, function ($q) use ($programId) {
                $q->where('academic_records.program_id', $programId);
            })
            ->groupBy('units.id', 'units.code', 'units.name')
            ->orderByDesc('fail_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'unit_code' => $item->code,
                    'unit_name' => $item->name,
                    'count' => (int) $item->fail_count,
                ];
            })
            ->toArray();
    }
}
