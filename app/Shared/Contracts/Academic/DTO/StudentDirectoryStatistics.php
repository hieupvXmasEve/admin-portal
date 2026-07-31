<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentDirectoryStatistics
{
    public function __construct(
        public int $totalStudents,
        public int $activeStudents,
        public int $enrolledStudents,
        public int $graduatedStudents,
        public int $suspendedStudents,
        public int $onLeaveStudents,
    ) {}

    /** @return array{total_students: int, active_students: int, enrolled_students: int, graduated_students: int, suspended_students: int, on_leave_students: int} */
    public function toArray(): array
    {
        return [
            'total_students' => $this->totalStudents,
            'active_students' => $this->activeStudents,
            'enrolled_students' => $this->enrolledStudents,
            'graduated_students' => $this->graduatedStudents,
            'suspended_students' => $this->suspendedStudents,
            'on_leave_students' => $this->onLeaveStudents,
        ];
    }
}
