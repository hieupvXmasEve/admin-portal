<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Student;
use App\Shared\Contracts\Academic\DTO\StudentDirectoryStatistics;
use App\Shared\Contracts\Academic\StudentDirectoryStatisticsReader;
use App\Shared\Contracts\Academic\StudentLifecycleMatcher;

final class EloquentStudentDirectoryStatisticsReader implements StudentDirectoryStatisticsReader
{
    public function __construct(private readonly StudentLifecycleMatcher $lifecycleMatcher) {}

    public function forCampus(int $campusId): StudentDirectoryStatistics
    {
        $studentIds = Student::query()
            ->where('campus_id', $campusId)
            ->pluck('id')
            ->all();
        $activeStudents = $this->countByStatuses($studentIds, ['intake_course', 'intake_pre_uni_gc']);

        return new StudentDirectoryStatistics(
            totalStudents: count($studentIds),
            activeStudents: $activeStudents,
            enrolledStudents: $activeStudents,
            graduatedStudents: $this->countByStatuses($studentIds, ['graduated']),
            suspendedStudents: $this->countByStatuses($studentIds, ['suspended']),
            onLeaveStudents: $this->countByStatuses($studentIds, ['inactive']),
        );
    }

    /** @param list<int> $studentIds @param list<string> $statuses */
    private function countByStatuses(array $studentIds, array $statuses): int
    {
        return count($this->lifecycleMatcher->matchingStudentIds($studentIds, $statuses));
    }
}
