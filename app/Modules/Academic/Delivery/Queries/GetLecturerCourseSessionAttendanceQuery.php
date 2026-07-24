<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseRosterReader;

final class GetLecturerCourseSessionAttendanceQuery
{
    public function __construct(private readonly CourseRosterReader $rosters) {}

    /** @return array<int, array<string, mixed>> */
    public function handle(int $lecturerId, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::query()
            ->whereHas('classSessions', static fn ($sessions) => $sessions->where('lecture_id', $lecturerId))
            ->with([
                'classSessions' => static fn ($sessions) => $sessions
                    ->where('lecture_id', $lecturerId)
                    ->with('attendances')
                    ->orderBy('session_date', 'asc'),
            ])
            ->whereKey($courseOfferingId)
            ->where('is_active', true)
            ->first();

        if ($courseOffering === null) {
            return [];
        }

        $activeStudentIds = collect($this->rosters->activeStudentIds($courseOffering->id));
        $expectedAttendees = $activeStudentIds->count();

        return $courseOffering->classSessions->map(static function ($session) use ($activeStudentIds, $expectedAttendees): array {
            $activeAttendances = $session->attendances->whereIn('student_id', $activeStudentIds);
            $actualAttendees = $activeAttendances->whereIn('status', ['present', 'late'])->count();
            $operationalPresenceRate = $expectedAttendees > 0
                ? round(($actualAttendees / $expectedAttendees) * 100, 1)
                : 0.0;

            return [
                'id' => $session->id,
                'title' => $session->session_title,
                'description' => $session->session_description,
                'session_date' => $session->session_date->format('Y-m-d'),
                'start_time' => $session->start_time->format('H:i'),
                'end_time' => $session->end_time->format('H:i'),
                'duration_minutes' => $session->duration_minutes,
                'session_type' => $session->session_type,
                'delivery_mode' => $session->delivery_mode,
                'status' => $session->status,
                'attendance_marked' => $activeAttendances->isNotEmpty(),
                'operational_presence_rate' => $operationalPresenceRate,
                'attendance_percentage' => $operationalPresenceRate,
                'expected_attendees' => $expectedAttendees,
                'actual_attendees' => $actualAttendees,
                'learning_objectives' => $session->learning_objectives,
                'topics_covered' => $session->topics_covered,
            ];
        })->all();
    }
}
