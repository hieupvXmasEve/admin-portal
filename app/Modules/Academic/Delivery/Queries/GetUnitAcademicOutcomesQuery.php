<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\AcademicRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Final-grade outcome aggregates (grade distribution, pass/fail, attendance
 * buckets, per-offering pass rates) for a unit.
 *
 * Lived in Progression while AcademicRecord was mapped there. Delivery writes
 * the record and owns it, so this aggregate sits with its data instead of
 * reaching back across the boundary.
 */
class GetUnitAcademicOutcomesQuery
{
    /**
     * @return array{
     *   grade_distribution: Collection<int, array{final_letter_grade: string, total: int}>,
     *   pass_fail: array<int, array{label: string, total: int, percentage: float}>,
     *   attendance: array<int, array{bucket: string, total: int, percentage: float}>,
     * }
     */
    public function handle(
        int $unitId,
        int $campusId,
        ?int $semesterId,
        ?int $offeringId,
        int|float|string $minAttendanceThreshold,
        int|float|string $displayMinAttendance,
    ): array {
        $baseQuery = fn () => $this->scopedQuery($unitId, $campusId, $semesterId, $offeringId);

        return [
            'grade_distribution' => $this->gradeDistribution($baseQuery()),
            'pass_fail' => $this->passFail($baseQuery()),
            'attendance' => $this->attendanceBuckets($baseQuery(), $minAttendanceThreshold, $displayMinAttendance),
        ];
    }

    /**
     * Final-grade pass rate for each of the given course offerings.
     *
     * @param  list<int>  $offeringIds
     * @return array<int, array{total: int, passed: int}>
     */
    public function passRatesByOffering(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return AcademicRecord::whereIn('course_offering_id', $offeringIds)
            ->where('grade_status', 'final')
            ->select(
                'course_offering_id',
                DB::raw('count(*) as total'),
                DB::raw('sum(CASE WHEN is_passed = 1 THEN 1 ELSE 0 END) as passed')
            )
            ->groupBy('course_offering_id')
            ->get()
            ->keyBy('course_offering_id')
            ->map(fn ($row): array => ['total' => (int) $row->total, 'passed' => (int) $row->passed])
            ->all();
    }

    private function scopedQuery(int $unitId, int $campusId, ?int $semesterId, ?int $offeringId)
    {
        $query = AcademicRecord::where('unit_id', $unitId)
            ->where('campus_id', $campusId);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        if ($offeringId) {
            $query->where('course_offering_id', $offeringId);
        }

        return $query;
    }

    /** @return Collection<int, array{final_letter_grade: string, total: int}> */
    private function gradeDistribution($query): Collection
    {
        $grades = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'F'];

        $rawGrades = $query->where('grade_status', 'final')
            ->select('final_letter_grade', DB::raw('count(*) as total'))
            ->groupBy('final_letter_grade')
            ->get()
            ->pluck('total', 'final_letter_grade');

        return collect($grades)->map(fn ($grade): array => [
            'final_letter_grade' => $grade,
            'total' => $rawGrades->get($grade, 0),
        ]);
    }

    /** @return array<int, array{label: string, total: int, percentage: float}> */
    private function passFail($query): array
    {
        $rawPassFail = $query->where('grade_status', 'final')
            ->select(
                DB::raw('CASE WHEN is_passed = 1 THEN "Pass" ELSE "Fail" END as result'),
                DB::raw('count(*) as total')
            )
            ->groupBy('result')
            ->get()
            ->pluck('total', 'result');

        $total = $rawPassFail->sum();

        return [
            [
                'label' => 'Pass',
                'total' => $rawPassFail->get('Pass', 0),
                'percentage' => $total > 0 ? round(($rawPassFail->get('Pass', 0) / $total) * 100, 1) : 0,
            ],
            [
                'label' => 'Fail',
                'total' => $rawPassFail->get('Fail', 0),
                'percentage' => $total > 0 ? round(($rawPassFail->get('Fail', 0) / $total) * 100, 1) : 0,
            ],
        ];
    }

    /** @return array<int, array{bucket: string, total: int, percentage: float}> */
    private function attendanceBuckets($query, int|float|string $minAttendanceThreshold, int|float|string $displayMinAttendance): array
    {
        $rawAttendance = $query->select(
            DB::raw("CASE WHEN attendance_percentage >= {$minAttendanceThreshold} THEN \">={$displayMinAttendance}%\" ELSE \"<{$displayMinAttendance}%\" END as bucket"),
            DB::raw('count(*) as total')
        )
            ->groupBy('bucket')
            ->get()
            ->pluck('total', 'bucket');

        $total = $rawAttendance->sum();

        return [
            [
                'bucket' => ">={$displayMinAttendance}%",
                'total' => $rawAttendance->get(">={$displayMinAttendance}%", 0),
                'percentage' => $total > 0 ? round(($rawAttendance->get(">={$displayMinAttendance}%", 0) / $total) * 100, 1) : 0,
            ],
            [
                'bucket' => "<{$displayMinAttendance}%",
                'total' => $rawAttendance->get("<{$displayMinAttendance}%", 0),
                'percentage' => $total > 0 ? round(($rawAttendance->get("<{$displayMinAttendance}%", 0) / $total) * 100, 1) : 0,
            ],
        ];
    }
}
