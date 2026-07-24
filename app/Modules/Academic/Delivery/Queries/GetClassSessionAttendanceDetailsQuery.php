<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use Illuminate\Database\Eloquent\Builder;

final class GetClassSessionAttendanceDetailsQuery
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(ClassSession $classSession, array $filters): array
    {
        $session = app(ClassSessionService::class)->getSessionWithRelations($classSession->id);
        $session->loadMissing('attendances');
        $attendanceQuery = $session->attendances()->with('student')->orderByDesc('created_at');

        $this->applyFilters($attendanceQuery, $filters);

        $studentsWithAttendance = $session->attendances()->pluck('student_id')->all();
        $studentsWithoutAttendance = $session->courseOffering->courseRegistrations()
            ->with('student')
            ->whereNotIn('student_id', $studentsWithAttendance)
            ->get()
            ->pluck('student');

        return [
            'session' => array_merge($session->toArray(), ['attendance_stats' => $session->attendanceStats]),
            'attendanceData' => $attendanceQuery->get(),
            'studentsWithoutAttendance' => $studentsWithoutAttendance,
            'statusOptions' => [
                'all' => 'All Statuses',
                'present' => 'Present',
                'absent' => 'Absent',
                'late' => 'Late',
                'excused' => 'Excused',
            ],
            'filters' => $filters,
        ];
    }

    /** @param Builder<Attendance> $query @param array<string, mixed> $filters */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', static function ($students) use ($search): void {
                $students->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
    }
}
