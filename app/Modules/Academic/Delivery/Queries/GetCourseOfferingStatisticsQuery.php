<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GetCourseOfferingStatisticsQuery
{
    /**
     * @return array{total_offerings: int, active_offerings: int, full_offerings: int, cancelled_offerings: int, total_enrollment: int, total_capacity: int, enrollment_rate: float|int}
     */
    public function handle(int $campusId, ?int $semesterId): array
    {
        $query = DB::table('course_offerings')
            ->where('campus_id', $campusId)
            ->when($semesterId !== null, fn (Builder $builder) => $builder->where('semester_id', $semesterId));

        $totalCapacity = (int) (clone $query)->sum('max_capacity');
        $totalEnrollment = (int) (clone $query)->sum('current_enrollment');

        return [
            'total_offerings' => (clone $query)->count(),
            'active_offerings' => (clone $query)->where('is_active', true)->where('enrollment_status', 'open')->count(),
            'full_offerings' => (clone $query)->whereColumn('current_enrollment', '>=', 'max_capacity')->count(),
            'cancelled_offerings' => (clone $query)->where('enrollment_status', 'cancelled')->count(),
            'total_enrollment' => $totalEnrollment,
            'total_capacity' => $totalCapacity,
            'enrollment_rate' => $totalCapacity > 0 ? round(($totalEnrollment / $totalCapacity) * 100, 2) : 0,
        ];
    }
}
