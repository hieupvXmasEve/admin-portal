<?php

declare(strict_types=1);

namespace App\Repositories\V1\Student;

use App\Models\ClassSession;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClassSessionRepository
{
    /**
     * Get class sessions for a student in a specific semester
     * Student can only see class sessions for course offerings they are registered for
     */
    public function getStudentClassSessions(Student $student, Semester $semester, array $filters = []): Collection
    {
        $query = ClassSession::query()
            ->whereHas('courseOffering', function (Builder $query) use ($student, $semester) {
                $query->where('semester_id', $semester->id)
                    ->whereHas('courseRegistrations', function (Builder $regQuery) use ($student) {
                        // Check if the student is registered for this specific course offering
                        $regQuery->where('student_id', $student->id)
                            ->whereIn('registration_status', ['pending', 'registered', 'confirmed']);
                    });
            })
            ->with([
                'courseOffering.unit',
                'courseOffering.lecture',
                'room',
            ]);

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Check if student is enrolled in a specific class session
     */
    public function isStudentEnrolledInSession(Student $student, ClassSession $classSession): bool
    {
        return $classSession->courseOffering
            ->courseRegistrations()
            ->where('student_id', $student->id)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
            ->exists();
    }

    /**
     * Get class sessions by day of week
     */
    public function getSessionsByDay(Student $student, Semester $semester, string $dayOfWeek): Collection
    {
        return $this->getStudentClassSessions($student, $semester, ['day_of_week' => $dayOfWeek]);
    }

    /**
     * Get class sessions by time range
     */
    public function getSessionsByTimeRange(Student $student, Semester $semester, string $startTime, string $endTime): Collection
    {
        return $this->getStudentClassSessions($student, $semester, [
            'time_range' => [
                'start' => $startTime,
                'end' => $endTime,
            ],
        ]);
    }

    /**
     * Get class sessions by lecturer
     */
    public function getSessionsByLecturer(Student $student, Semester $semester, int $lecturerId): Collection
    {
        return $this->getStudentClassSessions($student, $semester, ['lecturer_id' => $lecturerId]);
    }

    /**
     * Get class sessions by building
     */
    public function getSessionsByBuilding(Student $student, Semester $semester, string $building): Collection
    {
        return $this->getStudentClassSessions($student, $semester, ['building' => $building]);
    }

    /**
     * Get class sessions by session type
     */
    public function getSessionsByType(Student $student, Semester $semester, string $sessionType): Collection
    {
        return $this->getStudentClassSessions($student, $semester, ['session_type' => $sessionType]);
    }

    /**
     * Get class sessions for a specific course
     */
    public function getSessionsForCourse(Student $student, Semester $semester, string $courseCode): Collection
    {
        return $this->getStudentClassSessions($student, $semester, ['course_code' => $courseCode]);
    }

    /**
     * Get next upcoming class session for student
     */
    public function getNextUpcomingSession(Student $student): ?ClassSession
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return null;
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        // First, try to find a session today that hasn't started yet
        $todaySession = ClassSession::whereHas('courseOffering', function (Builder $query) use ($student, $currentSemester) {
            $query->where('semester_id', $currentSemester->id)
                ->whereHas('courseRegistrations', function (Builder $regQuery) use ($student) {
                    $regQuery->where('student_id', $student->id)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed']);
                });
        })
            ->where('day_of_week', $currentDay)
            ->where('start_time', '>', $currentTime)
            ->orderBy('start_time')
            ->first();

        if ($todaySession) {
            return $todaySession;
        }

        // If no session today, find the next session in the week
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentDayIndex = array_search($currentDay, $daysOfWeek);

        for ($i = 1; $i <= 7; $i++) {
            $nextDayIndex = ($currentDayIndex + $i) % 7;
            $nextDay = $daysOfWeek[$nextDayIndex];

            $nextSession = ClassSession::whereHas('courseOffering', function (Builder $query) use ($student, $currentSemester) {
                $query->where('semester_id', $currentSemester->id)
                    ->whereHas('courseRegistrations', function (Builder $regQuery) use ($student) {
                        $regQuery->where('student_id', $student->id)
                            ->whereIn('registration_status', ['pending', 'registered', 'confirmed']);
                    });
            })
                ->where('day_of_week', $nextDay)
                ->orderBy('start_time')
                ->first();

            if ($nextSession) {
                return $nextSession;
            }
        }

        return null;
    }

    /**
     * Get class sessions happening now
     */
    public function getCurrentSessions(Student $student): Collection
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return collect();
        }

        $currentDay = strtolower(now()->format('l'));
        $currentTime = now()->format('H:i');

        return ClassSession::whereHas('courseOffering', function (Builder $query) use ($student, $currentSemester) {
            $query->where('semester_id', $currentSemester->id)
                ->whereHas('courseRegistrations', function (Builder $regQuery) use ($student) {
                    $regQuery->where('student_id', $student->id)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed']);
                });
        })
            ->where('day_of_week', $currentDay)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>', $currentTime)
            ->with([
                'courseOffering.unit',
                'courseOffering.lecturer',
                'room',
            ])
            ->get();
    }

    /**
     * Get class sessions for today
     */
    public function getTodaySessions(Student $student): Collection
    {
        $currentSemester = Semester::where('is_active', true)->first();

        if (! $currentSemester) {
            return collect();
        }

        $currentDay = strtolower(now()->format('l'));

        return ClassSession::whereHas('courseOffering', function (Builder $query) use ($student, $currentSemester) {
            $query->where('semester_id', $currentSemester->id)
                ->whereHas('courseRegistrations', function (Builder $regQuery) use ($student) {
                    $regQuery->where('student_id', $student->id)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed']);
                });
        })
            ->where('day_of_week', $currentDay)
            ->with([
                'courseOffering.unit',
                'courseOffering.lecturer',
                'room',
            ])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['week_start'])) {
            $query->where('session_date', '>=', $filters['week_start']);
        }

        if (! empty($filters['week_end'])) {
            $query->where('session_date', '<=', $filters['week_end']);
        }

        if (! empty($filters['day_of_week'])) {
            // Convert day_of_week to actual dates within the week range
            if (! empty($filters['week_start']) && ! empty($filters['week_end'])) {
                $startDate = \Carbon\Carbon::parse($filters['week_start']);
                $endDate = \Carbon\Carbon::parse($filters['week_end']);

                $dayOfWeek = strtolower($filters['day_of_week']);
                $dayMap = [
                    'monday' => 1,
                    'tuesday' => 2,
                    'wednesday' => 3,
                    'thursday' => 4,
                    'friday' => 5,
                    'saturday' => 6,
                    'sunday' => 0
                ];

                if (isset($dayMap[$dayOfWeek])) {
                    $targetDay = $dayMap[$dayOfWeek];
                    $query->whereRaw('DAYOFWEEK(session_date) = ?', [$targetDay]);
                }
            }
        }

        if (! empty($filters['session_type'])) {
            $query->where('session_type', $filters['session_type']);
        }

        if (! empty($filters['lecturer_id'])) {
            $query->whereHas('courseOffering', function (Builder $q) use ($filters) {
                $q->where('lecturer_id', $filters['lecturer_id']);
            });
        }

        if (! empty($filters['lecturer_name'])) {
            $query->whereHas('courseOffering.lecturer', function (Builder $q) use ($filters) {
                $q->where('full_name', 'like', '%' . $filters['lecturer_name'] . '%');
            });
        }

        if (! empty($filters['building'])) {
            $query->whereHas('room', function (Builder $q) use ($filters) {
                $q->where('building', $filters['building']);
            });
        }

        if (! empty($filters['room_code'])) {
            $query->whereHas('room', function (Builder $q) use ($filters) {
                $q->where('code', $filters['room_code']);
            });
        }

        if (! empty($filters['course_code'])) {
            $query->whereHas('courseOffering.unit', function (Builder $q) use ($filters) {
                $q->where('code', 'like', '%' . $filters['course_code'] . '%');
            });
        }

        if (! empty($filters['time_range'])) {
            $startTime = $filters['time_range']['start'];
            $endTime = $filters['time_range']['end'];

            $query->where(function (Builder $q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function (Builder $subQ) use ($startTime, $endTime) {
                        $subQ->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });
        }

        if (! empty($filters['start_time_after'])) {
            $query->where('start_time', '>=', $filters['start_time_after']);
        }

        if (! empty($filters['end_time_before'])) {
            $query->where('end_time', '<=', $filters['end_time_before']);
        }
    }

    /**
     * Get class session statistics for student
     */
    public function getSessionStatistics(Student $student, Semester $semester): array
    {
        $sessions = $this->getStudentClassSessions($student, $semester);

        $totalSessions = $sessions->count();
        $uniqueCourses = $sessions->pluck('courseOffering.unit.code')->unique()->count();
        $totalHours = $sessions->sum(function ($session) {
            $start = \Carbon\Carbon::createFromTimeString($session->start_time);
            $end = \Carbon\Carbon::createFromTimeString($session->end_time);

            return $start->diffInMinutes($end) / 60;
        });

        $dayDistribution = $sessions->groupBy(function ($session) {
            $dayOfWeek = \Carbon\Carbon::parse($session->session_date)->dayOfWeek;
            $dayMap = [
                1 => 'sunday',
                2 => 'monday',
                3 => 'tuesday',
                4 => 'wednesday',
                5 => 'thursday',
                6 => 'friday',
                7 => 'saturday'
            ];
            return $dayMap[$dayOfWeek] ?? 'unknown';
        })->map->count();
        $sessionTypeDistribution = $sessions->groupBy('session_type')->map->count();
        $buildingDistribution = $sessions->groupBy('room.building')->map->count();

        return [
            'total_sessions_per_week' => $totalSessions,
            'unique_courses' => $uniqueCourses,
            'total_hours_per_week' => round($totalHours, 1),
            'average_session_duration' => $totalSessions > 0 ? (int) round(($totalHours * 60) / $totalSessions, 0) : 0,
            'day_distribution' => $dayDistribution instanceof \Illuminate\Support\Collection ? $dayDistribution->toArray() : (array) $dayDistribution,
            'session_type_distribution' => $sessionTypeDistribution instanceof \Illuminate\Support\Collection ? $sessionTypeDistribution->toArray() : (array) $sessionTypeDistribution,
            'building_distribution' => $buildingDistribution instanceof \Illuminate\Support\Collection ? $buildingDistribution->toArray() : (array) $buildingDistribution,
            'busiest_day' => $dayDistribution instanceof \Illuminate\Support\Collection ? $dayDistribution->keys()->sortByDesc(function ($day) use ($dayDistribution) {
                return $dayDistribution[$day];
            })->first() : null,
            'earliest_start' => $sessions->min('start_time'),
            'latest_end' => $sessions->max('end_time'),
        ];
    }
}
