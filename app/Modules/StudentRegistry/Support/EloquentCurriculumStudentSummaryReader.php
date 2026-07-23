<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Shared\Contracts\StudentRegistry\CurriculumStudentSummaryReader;
use Illuminate\Support\Facades\DB;

final class EloquentCurriculumStudentSummaryReader implements CurriculumStudentSummaryReader
{
    public function summaryForCurriculumVersion(int $curriculumVersionId, int $campusId): array
    {
        $statusCounts = DB::table('students')
            ->where('campus_id', $campusId)
            ->where('curriculum_version_id', $curriculumVersionId)
            ->whereNull('deleted_at')
            ->selectRaw('academic_status, COUNT(*) as count')
            ->groupBy('academic_status')
            ->pluck('count', 'academic_status')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        $counts = [
            'active' => $statusCounts['active'] ?? 0,
            'inactive' => $statusCounts['inactive'] ?? 0,
            'graduated' => $statusCounts['graduated'] ?? 0,
            'suspended' => $statusCounts['suspended'] ?? 0,
            'withdrawn' => $statusCounts['withdrawn'] ?? 0,
        ];
        $total = array_sum($counts);

        return [
            'counts' => $counts,
            'total' => $total,
            'enrollment_trends' => DB::table('students')
                ->where('campus_id', $campusId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('deleted_at')
                ->where('admission_date', '>=', now()->subMonths(6))
                ->selectRaw('YEAR(admission_date) as year, MONTH(admission_date) as month, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(static fn ($item): array => ['period' => $item->year.'-'.str_pad((string) $item->month, 2, '0', STR_PAD_LEFT), 'count' => (int) $item->count])
                ->all(),
            'graduation_projections' => DB::table('students')
                ->where('campus_id', $campusId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->whereNull('deleted_at')
                ->where('academic_status', 'active')
                ->whereNotNull('expected_graduation_date')
                ->selectRaw('YEAR(expected_graduation_date) as year, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year')
                ->pluck('count', 'year')
                ->map(static fn ($count): int => (int) $count)
                ->all(),
            'statistics' => [
                'active_percentage' => $total > 0 ? round(($counts['active'] / $total) * 100, 2) : 0.0,
                'graduation_rate' => $total > 0 ? round(($counts['graduated'] / $total) * 100, 2) : 0.0,
                'retention_rate' => $total > 0 ? round((($counts['active'] + $counts['inactive']) / $total) * 100, 2) : 0.0,
            ],
        ];
    }
}
