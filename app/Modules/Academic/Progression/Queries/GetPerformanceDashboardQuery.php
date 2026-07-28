<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\GpaCalculation;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;

class GetPerformanceDashboardQuery
{
    public function handle(int $campusId, int $semesterId): array
    {
        // 1. Overview Stats for the selected semester
        $stats = GpaCalculation::query()
            ->where('semester_id', $semesterId)
            ->where('is_finalized', true)
            ->whereHas('student', fn ($q) => $q->where('campus_id', $campusId))
            ->selectRaw('
                AVG(semester_gpa) as avg_semester_gpa,
                AVG(cumulative_gpa) as avg_cumulative_gpa,
                COUNT(*) as total_students,
                SUM(CASE WHEN academic_standing IN (?, ?, ?) THEN 1 ELSE 0 END) as at_risk_count,
                AVG(CASE WHEN semester_credit_points > 0 THEN (semester_credit_points_earned / semester_credit_points) * 100 ELSE 0 END) as avg_completion_rate
            ', ['warning', 'probation', 'suspension'])
            ->first();

        // 2. Academic Standing Distribution
        $standingDistribution = GpaCalculation::query()
            ->where('semester_id', $semesterId)
            ->where('is_finalized', true)
            ->whereHas('student', fn ($q) => $q->where('campus_id', $campusId))
            ->select('academic_standing', DB::raw('count(*) as count'))
            ->groupBy('academic_standing')
            ->get()
            ->map(fn ($item) => [
                'name' => ucfirst($item->academic_standing),
                'value' => $item->count,
                'color' => $this->getStandingColor($item->academic_standing),
            ]);

        // 3. GPA Trend (Last 5 semesters)
        // Get last 5 semesters ending with the selected one
        $targetSemester = Semester::find($semesterId);
        $semesterIds = Semester::query()
            ->where('start_date', '<=', $targetSemester->start_date)
            ->orderBy('start_date', 'desc')
            ->limit(5)
            ->pluck('id');

        $trend = GpaCalculation::query()
            ->whereIn('semester_id', $semesterIds)
            ->where('is_finalized', true)
            ->whereHas('student', fn ($q) => $q->where('campus_id', $campusId))
            ->join('semesters', 'gpa_calculations.semester_id', '=', 'semesters.id')
            ->select('semesters.code as semester_code', 'semesters.start_date')
            ->selectRaw('AVG(semester_gpa) as avg_gpa')
            ->groupBy('semesters.code', 'semesters.start_date')
            ->orderBy('semesters.start_date', 'asc')
            ->get()
            ->map(fn ($item) => [
                'semester' => $item->semester_code,
                'gpa' => round((float) $item->avg_gpa, 2),
            ]);

        // 4. Top Performing Students (by Semester GPA)
        $topStudents = GpaCalculation::query()
            ->with(['student'])
            ->where('semester_id', $semesterId)
            ->where('is_finalized', true)
            ->whereHas('student', fn ($q) => $q->where('campus_id', $campusId))
            ->orderByDesc('semester_gpa')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->student->id,
                'name' => $item->student->full_name,
                'student_id' => $item->student->student_id,
                'gpa' => $item->semester_gpa,
                'program' => $item->program->code ?? '',
            ]);

        // 5. At Risk Students (Bottom GPA or Bad Standing)
        $atRiskList = GpaCalculation::query()
            ->with(['student'])
            ->where('semester_id', $semesterId)
            ->where('is_finalized', true)
            ->whereHas('student', fn ($q) => $q->where('campus_id', $campusId))
            ->where(function ($q) {
                $q->whereIn('academic_standing', ['warning', 'probation', 'suspension'])
                    ->orWhere('cumulative_gpa', '<', 2.0);
            })
            ->orderBy('cumulative_gpa', 'asc')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->student->id,
                'name' => $item->student->full_name,
                'student_id' => $item->student->student_id,
                'gpa' => $item->cumulative_gpa,
                'standing' => $item->academic_standing,
            ]);

        return [
            'overview' => [
                'avg_semester_gpa' => round((float) ($stats->avg_semester_gpa ?? 0), 2),
                'avg_cumulative_gpa' => round((float) ($stats->avg_cumulative_gpa ?? 0), 2),
                'total_students' => (int) ($stats->total_students ?? 0),
                'at_risk_count' => (int) ($stats->at_risk_count ?? 0),
                'avg_completion_rate' => round((float) ($stats->avg_completion_rate ?? 0), 1),
            ],
            'distribution' => $standingDistribution,
            'trend' => $trend,
            'top_students' => $topStudents,
            'at_risk_list' => $atRiskList,
        ];
    }

    private function getStandingColor(string $standing): string
    {
        return match ($standing) {
            'normal' => '#22c55e', // green-500
            'warning' => '#f59e0b', // amber-500
            'probation' => '#ef4444', // red-500
            'suspension' => '#7f1d1d', // red-900
            default => '#94a3b8', // slate-400
        };
    }
}
