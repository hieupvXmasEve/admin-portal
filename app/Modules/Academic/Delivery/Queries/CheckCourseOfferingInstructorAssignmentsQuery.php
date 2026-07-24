<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Support\Facades\DB;

final class CheckCourseOfferingInstructorAssignmentsQuery
{
    /** @return array<string,mixed> */
    public function handle(int $campusId, int $academicPeriodId): array
    {
        $period = app(CourseOfferingCatalogReader::class)->offeringPeriod($academicPeriodId);
        $offerings = DB::table('course_offerings')
            ->where('campus_id', $campusId)
            ->where('semester_id', $academicPeriodId)
            ->where('is_active', true)
            ->whereNull('lecture_id')
            ->get(['id', 'unit_id', 'section_code', 'current_enrollment', 'max_capacity']);
        $catalog = app(CourseOfferingCatalogReader::class);

        return [
            'semester_id' => $academicPeriodId,
            'semester_name' => $period?->name ?? 'Unknown',
            'classes_started' => $period?->start_date?->isPast() ?? false,
            'offerings_without_instructors' => $offerings->count(),
            'unassigned_offerings' => $offerings->map(function (object $offering) use ($catalog): array {
                $unit = $catalog->offeringUnit((int) $offering->unit_id);

                return [
                    'id' => $offering->id,
                    'course_code' => $unit?->code ?? 'N/A',
                    'course_title' => $unit?->name ?? 'N/A',
                    'section_code' => $offering->section_code,
                    'current_enrollment' => $offering->current_enrollment,
                    'max_capacity' => $offering->max_capacity,
                ];
            })->values()->all(),
            'is_ready_for_classes' => $offerings->isEmpty(),
            'warning_message' => $offerings->isNotEmpty() && ($period?->start_date?->isPast() ?? false)
                ? 'Classes have started but some course offerings do not have assigned instructors!'
                : null,
        ];
    }
}
