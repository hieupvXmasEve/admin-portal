<?php

declare(strict_types=1);

namespace App\Actions\Unit;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class GetUnitStatisticsAction
{
    /**
     * Get statistics for a specific unit.
     */
    public function execute(int $unitId, array $filters = []): array
    {
        $unit = Unit::findOrFail($unitId);
        $campusId = session('current_campus_id');
        $semesterId = $filters['semester_id'] ?? null;

        $query = AcademicRecord::where('unit_id', $unitId)
            ->where('campus_id', $campusId);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        $gradeQuery = clone $query;
        $passFailQuery = clone $query;
        $attendanceQuery = clone $query;
        $syllabusQuery = clone $query;

        // 0. Get thresholds from the most recent active syllabus template
        $activeSyllabus = $unit->activeSyllabusTemplates()->where('is_default', true)->first() 
            ?? $unit->activeSyllabusTemplates()->orderBy('version', 'desc')->first();
        
        $minAttendance = $activeSyllabus?->min_attendance_threshold ?? 80.00;
        $minGrade = $activeSyllabus?->min_grade_threshold ?? 60.00;

        // 1. Grade Distribution (final grades only)
        $grades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F'];
        $rawGrades = $gradeQuery->where('grade_status', 'final')
            ->select('final_letter_grade', DB::raw('count(*) as total'))
            ->groupBy('final_letter_grade')
            ->get()
            ->pluck('total', 'final_letter_grade');

        $gradeDistribution = collect($grades)->map(function ($grade) use ($rawGrades) {
            return [
                'final_letter_grade' => $grade,
                'total' => $rawGrades->get($grade, 0),
            ];
        });

        // 2. Pass/Fail Distribution (final grades only)
        $rawPassFail = $passFailQuery->where('grade_status', 'final')
            ->select(
                DB::raw('CASE WHEN is_passed = 1 THEN "Pass" ELSE "Fail" END as result'),
                DB::raw('count(*) as total')
            )
            ->groupBy('result')
            ->get()
            ->pluck('total', 'result');

        $passFail = [
            ['label' => 'Pass', 'total' => $rawPassFail->get('Pass', 0)],
            ['label' => 'Fail', 'total' => $rawPassFail->get('Fail', 0)],
        ];

        // 3. Attendance Buckets (using syllabus threshold)
        $rawAttendance = $attendanceQuery->select(
            DB::raw("CASE WHEN attendance_percentage >= {$minAttendance} THEN \">={$minAttendance}%\" ELSE \"<{$minAttendance}%\" END as bucket"),
            DB::raw('count(*) as total')
        )
            ->groupBy('bucket')
            ->get()
            ->pluck('total', 'bucket');

        $attendance = [
            ['bucket' => ">={$minAttendance}%", 'total' => $rawAttendance->get(">={$minAttendance}%", 0)],
            ['bucket' => "<{$minAttendance}%", 'total' => $rawAttendance->get("<{$minAttendance}%", 0)],
        ];

        // 4. Offerings List
        $offeringsQuery = CourseOffering::with(['semester', 'lecture'])
            ->where('unit_id', $unitId)
            ->where('campus_id', $campusId);

        if ($semesterId) {
            $offeringsQuery->where('semester_id', $semesterId);
        }

        $offerings = $offeringsQuery->get()->map(function ($offering) {
            $stats = AcademicRecord::where('course_offering_id', $offering->id)
                ->where('grade_status', 'final')
                ->select(
                    DB::raw('count(*) as total'),
                    DB::raw('sum(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed')
                )
                ->first();

            return [
                'id' => $offering->id,
                'section' => $offering->section_code,
                'semester' => $offering->semester?->name,
                'lecturer' => $offering->lecture?->full_name,
                'enrollment' => $offering->current_enrollment,
                'pass_rate' => $stats->total > 0 ? round(($stats->passed / $stats->total) * 100, 2) : 0,
            ];
        });

        return [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'min_attendance_threshold' => $minAttendance,
                'min_grade_threshold' => $minGrade,
            ],
            'grade_distribution' => $gradeDistribution,
            'pass_fail' => $passFail,
            'attendance' => $attendance,
            'offerings' => $offerings,
        ];
    }
}
