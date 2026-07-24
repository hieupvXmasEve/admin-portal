<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class ListClassSessionsQuery
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $sessions = app(ClassSessionService::class)->getPaginatedSessions($filters, $perPage);
        $sessions->getCollection()->transform(static function ($session) {
            $session->attendance_stats = $session->attendanceStats;

            return $session;
        });

        $filters['per_page'] = $perPage;

        return [
            'sessions' => $sessions,
            'filters' => $filters,
            'statusOptions' => [
                'scheduled' => 'Scheduled',
                'in_progress' => 'In Progress',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
            ],
            'sessionTypeOptions' => [
                'lecture' => 'Lecture',
                'tutorial' => 'Tutorial',
                'practical' => 'Practical',
                'workshop' => 'Workshop',
                'seminar' => 'Seminar',
                'exam' => 'Exam',
            ],
            'deliveryModeOptions' => [
                'in_person' => 'In Person',
                'online' => 'Online',
                'hybrid' => 'Hybrid',
            ],
        ];
    }
}
