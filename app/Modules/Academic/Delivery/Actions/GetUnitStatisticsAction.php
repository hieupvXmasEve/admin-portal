<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Catalog\Queries\GetUnitGradingThresholdsQuery;
use App\Modules\Academic\Delivery\Queries\GetUnitAcademicOutcomesQuery;

class GetUnitStatisticsAction
{
    public function __construct(
        private readonly GetUnitGradingThresholdsQuery $thresholdsQuery,
        private readonly GetUnitAcademicOutcomesQuery $outcomesQuery,
    ) {}

    /**
     * Get statistics for a specific unit.
     */
    public function execute(int $unitId, array $filters = []): array
    {
        $campusId = session('current_campus_id');
        $semesterId = $filters['semester_id'] ?? null;
        $offeringId = $filters['course_offering_id'] ?? null;

        $unitContext = $this->thresholdsQuery->handle($unitId);
        $minAttendance = $unitContext['min_attendance_threshold'];
        $minGrade = $unitContext['min_grade_threshold'];

        // Clean up attendance threshold for display (remove decimals if whole number)
        $displayMinAttendance = (floatval($minAttendance) == intval($minAttendance))
            ? intval($minAttendance)
            : $minAttendance;

        $outcomes = $this->outcomesQuery->handle(
            $unitId,
            $campusId,
            $semesterId,
            $offeringId,
            $minAttendance,
            $displayMinAttendance,
        );

        // Offerings List
        $offeringsQuery = CourseOffering::with(['semester', 'lecture'])
            ->where('unit_id', $unitId)
            ->where('campus_id', $campusId);

        if ($semesterId) {
            $offeringsQuery->where('semester_id', $semesterId);
        }

        $offeringModels = $offeringsQuery->get();
        $passRatesByOffering = $this->outcomesQuery->passRatesByOffering($offeringModels->pluck('id')->all());

        $offerings = $offeringModels->map(function ($offering) use ($passRatesByOffering) {
            $stats = $passRatesByOffering[$offering->id] ?? ['total' => 0, 'passed' => 0];

            return [
                'id' => $offering->id,
                'section' => $offering->section_code,
                'semester' => $offering->semester?->name,
                'lecturer' => $offering->lecture?->full_name,
                'enrollment' => $offering->current_enrollment,
                'pass_rate' => $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100, 2) : 0,
            ];
        });

        return [
            'unit' => [
                'id' => $unitContext['id'],
                'code' => $unitContext['code'],
                'name' => $unitContext['name'],
                'min_attendance_threshold' => $minAttendance,
                'min_grade_threshold' => $minGrade,
            ],
            'grade_distribution' => $outcomes['grade_distribution'],
            'pass_fail' => $outcomes['pass_fail'],
            'attendance' => $outcomes['attendance'],
            'offerings' => $offerings,
        ];
    }
}
