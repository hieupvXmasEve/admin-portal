<?php

declare(strict_types=1);

namespace App\Services\V1\Lecturer;

use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Unit;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LecturerCourseService
{
    /**
     * Get lecturer's course offerings with filtering and pagination
     * Only returns course offerings where the lecturer is assigned to at least one class session
     */
    public function getCourseOfferings(
        Lecture $lecturer,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        // Changed from lecturer->courseOfferings() to CourseOffering model query
        // Filter by course offerings that have at least one class session assigned to this lecturer
        $query = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->with([
                'unit',
                'semester',
                'classRosterRegistrations.student',
                'classSessions' => function ($q) use ($lecturer) {
                    $q->where('lecture_id', $lecturer->id)
                        ->with('attendances')
                        ->orderBy('session_date', 'desc');
                },
            ])
            ->where('is_active', true);

        // Apply filters
        $this->applyCourseFilters($query, $filters);

        return $query->orderBy('semester_id', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get detailed course offering information
     */
    public function getCourseOfferingDetails(Lecture $lecturer, int $courseOfferingId): ?array
    {
        $cacheKey = "lecturer-course-details:v2:{$lecturer->id}:{$courseOfferingId}";

        return Cache::remember($cacheKey, 600, function () use ($lecturer, $courseOfferingId) {
            // Only get course offering if lecturer is assigned to at least one class session
            $courseOffering = CourseOffering::query()
                ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                    $sessionQuery->where('lecture_id', $lecturer->id);
                })
                ->with([
                    'unit',
                    'semester',
                    'classRosterRegistrations.student',
                    'classSessions' => function ($q) use ($lecturer) {
                        $q->where('lecture_id', $lecturer->id)
                            ->with('attendances')
                            ->orderBy('session_date', 'asc');
                    },
                ])
                ->where('id', $courseOfferingId)
                ->where('is_active', true)
                ->first();

            if (! $courseOffering) {
                return null;
            }

            return [
                'course_offering' => $this->formatCourseOffering($courseOffering),
                'enrollment_statistics' => $this->getEnrollmentStatistics($courseOffering),
                'attendance_statistics' => $this->getAttendanceStatistics($courseOffering),
                'session_overview' => $this->getSessionOverview($courseOffering),
                'student_performance' => $this->getStudentPerformanceOverview($courseOffering),
            ];
        });
    }

    /**
     * Get unit information by course offering id
     */
    public function getUnitByCourseOfferingId(Lecture $lecturer, int $courseOfferingId): ?Unit
    {
        // Only get course offering if lecturer is assigned to at least one class session
        $courseOffering = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (! $courseOffering) {
            return null;
        }

        return $courseOffering->unit;
    }

    /**
     * Get course statistics for lecturer
     */
    public function getCourseStatistics(Lecture $lecturer, int $courseOfferingId): array
    {
        // Only get course offering if lecturer is assigned to at least one class session
        $courseOffering = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->with([
                'classRosterRegistrations',
                'classSessions' => function ($q) use ($lecturer) {
                    $q->where('lecture_id', $lecturer->id)
                        ->with('attendances');
                },
            ])
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (! $courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        return [
            'enrollment_stats' => $this->getDetailedEnrollmentStats($courseOffering),
            'attendance_stats' => $this->getDetailedAttendanceStats($courseOffering),
            'session_stats' => $this->getDetailedSessionStats($courseOffering),
            'performance_trends' => $this->getPerformanceTrends($courseOffering),
            'weekly_breakdown' => $this->getWeeklyBreakdown($courseOffering),
        ];
    }

    /**
     * Get enrolled students for a course
     */
    public function getCourseStudents(
        Lecture $lecturer,
        int $courseOfferingId,
        array $filters = []
    ): array {
        // Only get course offering if lecturer is assigned to at least one class session
        $courseOffering = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->with('syllabusTemplate')
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();
        if (! $courseOffering) {
            throw new \Exception('Course offering not found or access denied');
        }

        $studentsQuery = $courseOffering->courseRegistrations()
            ->with([
                'student',
                'student.attendances' => function ($q) use ($courseOfferingId) {
                    $q->whereHas('classSession', function ($sessionQuery) use ($courseOfferingId) {
                        $sessionQuery->where('course_offering_id', $courseOfferingId);
                    });
                },
                'student.academicRecords' => function ($q) use ($courseOfferingId) {
                    $q->where('course_offering_id', $courseOfferingId);
                },
                'student.academicStandings' => function ($q) use ($courseOffering) {
                    $q->where('semester_id', $courseOffering->semester_id)
                        ->where('is_active', true)
                        ->latest('effective_date');
                },
            ])
            ->visibleForClassRoster();

        // Apply student filters
        $this->applyStudentFilters($studentsQuery, $filters);

        $students = $studentsQuery->get();
        $attendanceThreshold = (float) ($courseOffering->syllabusTemplate?->min_attendance_threshold ?? 80.00);

        return $students->map(function ($registration) use ($courseOfferingId, $attendanceThreshold) {
            $student = $registration->student;
            $isRosterActive = $registration->isClassRosterActive();
            $attendanceStats = $this->calculateStudentAttendanceStats($student, $courseOfferingId);
            $academicRecord = $student->academicRecords->first();
            $academicStanding = $student->academicStandings->first();
            $finalScore = $this->calculateStudentFinalScore($student->id, $courseOfferingId);
            $meetsAttendanceRequirement = $academicRecord?->meets_attendance_requirement ??
                ($attendanceStats['percentage'] >= $attendanceThreshold);

            return [
                'student_id' => $student->id,
                'student_number' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'registration_date' => $registration->created_at->format('Y-m-d'),
                'is_roster_active' => $isRosterActive,
                'roster_status' => $registration->classRosterStatus(),
                'roster_status_label' => $registration->classRosterStatusLabel(),
                'can_mark_attendance' => $isRosterActive,

                // Course Registration Information
                'registration_status' => $registration->registration_status,
                'attempt_number' => $registration->attempt_number ?? 1,
                'is_retake' => $registration->is_retake ?? false,

                // Academic Scores and Grades
                'final_score' => $finalScore,
                'final_grade' => $academicRecord?->final_letter_grade ?? $registration->final_grade,
                'grade_status' => $academicRecord?->grade_status ?? 'provisional',

                // Attendance and Academic Standing
                'attendance_percentage' => $attendanceStats['percentage'],
                'sessions_attended' => $attendanceStats['attended'],
                'total_sessions' => $attendanceStats['total'],
                'last_attendance' => $attendanceStats['last_attendance'],
                'meets_attendance_requirement' => $isRosterActive && $meetsAttendanceRequirement,
                'academic_standing' => $academicStanding?->standing ?? 'good',
                'academic_standing_label' => $academicStanding?->standing_label ?? 'Good Standing',

                'status' => $isRosterActive
                    ? $this->getStudentStatus($attendanceStats, $attendanceThreshold)
                    : 'inactive',
            ];
        })->toArray();
    }

    /**
     * Get filter options for courses
     */
    public function getFilterOptions(Lecture $lecturer): array
    {
        // Get semesters from course offerings where lecturer has class sessions
        $courseOfferings = CourseOffering::query()
            ->whereHas('classSessions', function ($sessionQuery) use ($lecturer) {
                $sessionQuery->where('lecture_id', $lecturer->id);
            })
            ->with('semester')
            ->get();

        $semesters = $courseOfferings
            ->pluck('semester')
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();

        $deliveryModes = $courseOfferings
            ->pluck('delivery_mode')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'semesters' => $semesters->map(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'is_active' => (bool) $semester->is_active,
                ];
            }),
            'delivery_modes' => $deliveryModes->map(function ($mode) {
                return [
                    'value' => $mode,
                    'label' => ucfirst(str_replace('_', ' ', $mode)),
                ];
            }),
            'enrollment_statuses' => [
                ['value' => 'open', 'label' => 'Open for Enrollment'],
                ['value' => 'closed', 'label' => 'Enrollment Closed'],
                ['value' => 'full', 'label' => 'Full Capacity'],
            ],
        ];
    }

    /**
     * Apply course filters to query
     */
    protected function applyCourseFilters($query, array $filters): void
    {
        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['delivery_mode'])) {
            $query->where('delivery_mode', $filters['delivery_mode']);
        }

        if (! empty($filters['enrollment_status'])) {
            switch ($filters['enrollment_status']) {
                case 'open':
                    $query->where('enrollment_status', 'open')
                        ->whereColumn('current_enrollment', '<', 'max_capacity');
                    break;
                case 'closed':
                    $query->where('enrollment_status', 'closed');
                    break;
                case 'full':
                    $query->whereColumn('current_enrollment', '>=', 'max_capacity');
                    break;
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('unit', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Apply student filters to query
     */
    protected function applyStudentFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['attendance_status'])) {
            // This would require a complex subquery to filter by attendance percentage
            // Implementation depends on specific requirements
        }
    }

    /**
     * Format course offering data
     */
    protected function formatCourseOffering(CourseOffering $courseOffering): array
    {
        $rosterStats = $this->getRosterEnrollmentStats($courseOffering);

        return [
            'id' => $courseOffering->id,
            'section_code' => $courseOffering->section_code,
            'delivery_mode' => $courseOffering->delivery_mode,
            'location' => $courseOffering->location,
            'max_capacity' => $courseOffering->max_capacity,
            'current_enrollment' => $rosterStats['visible_roster_students'],
            'active_roster_students' => $rosterStats['active_roster_students'],
            'visible_roster_students' => $rosterStats['visible_roster_students'],
            'enrollment_status' => $courseOffering->enrollment_status,
            'schedule_days' => $courseOffering->schedule_days,
            'schedule_time_start' => $courseOffering->schedule_time_start?->format('H:i'),
            'schedule_time_end' => $courseOffering->schedule_time_end?->format('H:i'),
            'unit' => [
                'id' => $courseOffering->unit->id,
                'code' => $courseOffering->unit->code,
                'name' => $courseOffering->unit->name,
                'credit_points' => $courseOffering->unit->credit_points,
                'description' => $courseOffering->unit->description,
            ],
            'semester' => [
                'id' => $courseOffering->semester->id,
                'name' => $courseOffering->semester->name,
                'code' => $courseOffering->semester->code,
                'start_date' => $courseOffering->semester->start_date?->format('Y-m-d'),
                'end_date' => $courseOffering->semester->end_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get enrollment statistics
     */
    protected function getEnrollmentStatistics(CourseOffering $courseOffering): array
    {
        $totalRegistrations = $courseOffering->courseRegistrations()->count();
        $rosterStats = $this->getRosterEnrollmentStats($courseOffering);
        $enrolledStudents = $rosterStats['visible_roster_students'];
        $waitlistedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'waitlisted')->count();
        $droppedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'dropped')->count();

        return [
            'total_registrations' => $totalRegistrations,
            'enrolled_students' => $enrolledStudents,
            'active_roster_students' => $rosterStats['active_roster_students'],
            'visible_roster_students' => $rosterStats['visible_roster_students'],
            'inactive_roster_students' => $rosterStats['inactive_roster_students'],
            'completed_students' => $rosterStats['completed_students'],
            'deferred_students' => $rosterStats['deferred_students'],
            'waitlisted_students' => $waitlistedStudents,
            'dropped_students' => $droppedStudents,
            'capacity_utilization' => $courseOffering->max_capacity > 0
                ? round(($enrolledStudents / $courseOffering->max_capacity) * 100, 1)
                : 0,
            'available_spots' => max(0, $courseOffering->max_capacity - $enrolledStudents),
            'active_available_spots' => max(0, $courseOffering->max_capacity - $rosterStats['active_roster_students']),
        ];
    }

    protected function getRosterEnrollmentStats(CourseOffering $courseOffering): array
    {
        $registrations = $courseOffering->relationLoaded('classRosterRegistrations')
            ? $courseOffering->classRosterRegistrations
            : $courseOffering->classRosterRegistrations()->with('student')->get();

        $activeRosterStudents = $registrations
            ->filter(fn ($registration) => $registration->isClassRosterActive())
            ->count();
        $visibleRosterStudents = $registrations->count();

        return [
            'active_roster_students' => $activeRosterStudents,
            'visible_roster_students' => $visibleRosterStudents,
            'inactive_roster_students' => $visibleRosterStudents - $activeRosterStudents,
            'completed_students' => $registrations
                ->where('registration_status', 'completed')
                ->count(),
            'deferred_students' => $registrations
                ->filter(fn ($registration) => in_array($registration->classRosterStatus(), ['defer', 'deferred'], true))
                ->count(),
        ];
    }

    /**
     * Get attendance statistics for course
     */
    protected function getAttendanceStatistics(CourseOffering $courseOffering): array
    {
        $sessions = $courseOffering->classSessions;
        $activeStudentIds = $courseOffering->activeClassRosterStudentIds();
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $sessionsWithAttendance = $sessions
            ->filter(function ($session) use ($activeStudentIds) {
                if (! $session->relationLoaded('attendances')) {
                    return $session->attendance_marked;
                }

                return $session->attendances
                    ->whereIn('student_id', $activeStudentIds)
                    ->isNotEmpty();
            })
            ->count();

        $attendanceRecords = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->whereIn('attendances.student_id', $activeStudentIds)
            ->whereIn('attendances.status', ['present', 'late'])
            ->count();

        $totalPossibleAttendances = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->whereIn('attendances.student_id', $activeStudentIds)
            ->count();

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'sessions_with_attendance' => $sessionsWithAttendance,
            'pending_attendance' => $completedSessions - $sessionsWithAttendance,
            'overall_attendance_rate' => $totalPossibleAttendances > 0
                ? round(($attendanceRecords / $totalPossibleAttendances) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get session overview
     */
    protected function getSessionOverview(CourseOffering $courseOffering): array
    {
        $sessions = $courseOffering->classSessions;

        return [
            'total_sessions' => $sessions->count(),
            'upcoming_sessions' => $sessions->where('session_date', '>=', now())->count(),
            'completed_sessions' => $sessions->where('status', 'completed')->count(),
            'cancelled_sessions' => $sessions->where('status', 'cancelled')->count(),
            'next_session' => $this->getNextSession($sessions),
        ];
    }

    /**
     * Get next session details
     */
    protected function getNextSession($sessions): ?array
    {
        $nextSession = $sessions->where('session_date', '>=', now())
            ->sortBy('session_date')
            ->first();

        if (! $nextSession) {
            return null;
        }

        return [
            'id' => $nextSession->id,
            'title' => $nextSession->session_title,
            'date' => $nextSession->session_date->format('Y-m-d'),
            'start_time' => $nextSession->start_time->format('H:i'),
            'end_time' => $nextSession->end_time->format('H:i'),
            'delivery_mode' => $nextSession->delivery_mode,
        ];
    }

    /**
     * Get student performance overview (placeholder)
     */
    protected function getStudentPerformanceOverview(CourseOffering $courseOffering): array
    {
        // This would integrate with grading system
        return [
            'average_grade' => null,
            'grade_distribution' => [],
            'at_risk_students' => 0,
        ];
    }

    /**
     * Calculate student attendance statistics
     */
    protected function calculateStudentAttendanceStats($student, int $courseOfferingId): array
    {
        $attendances = $student->attendances()
            ->whereHas('classSession', function ($q) use ($courseOfferingId) {
                $q->where('course_offering_id', $courseOfferingId);
            })
            ->get();

        // Count unique sessions the student has attendance records for
        $uniqueSessionIds = $attendances->pluck('class_session_id')->unique();
        $totalSessions = $uniqueSessionIds->count();

        // Count unique sessions where student was present or late
        $attendedSessionIds = $attendances
            ->whereIn('status', ['present', 'late'])
            ->pluck('class_session_id')
            ->unique();
        $attendedSessions = $attendedSessionIds->count();

        $percentage = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100, 1) : 0;

        $lastAttendance = $attendances->sortByDesc('created_at')->first();

        return [
            'total' => $totalSessions,
            'attended' => $attendedSessions,
            'percentage' => $percentage,
            'last_attendance' => $lastAttendance?->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Calculate student's final score from assessment components
     */
    protected function calculateStudentFinalScore(int $studentId, int $courseOfferingId): ?float
    {
        try {
            // Get course offering to access syllabus
            $courseOffering = CourseOffering::with([
                'syllabus.assessmentComponents.details.scores' => function ($q) use ($studentId) {
                    $q->where('student_id', $studentId)
                        ->where('score_excluded', false)
                        ->whereIn('score_status', ['final', 'graded'])
                        ->whereNotNull('percentage_score')
                        ->orderBy('graded_at', 'desc');
                },
            ])->find($courseOfferingId);

            if (! $courseOffering || ! $courseOffering->syllabus) {
                return null;
            }

            $totalWeightedScore = 0;
            $totalWeight = 0;

            foreach ($courseOffering->syllabus->assessmentComponents as $component) {
                $componentWeight = $component->weight ?? 0;

                if ($componentWeight <= 0) {
                    continue;
                }

                $componentScore = $this->calculateComponentScore($component, $studentId);

                if ($componentScore !== null) {
                    $totalWeightedScore += ($componentScore * $componentWeight);
                    $totalWeight += $componentWeight;
                }
            }

            // Only return a score if we have some assessment data
            if ($totalWeight > 0) {
                return round($totalWeightedScore / $totalWeight, 2);
            }

            return null;
        } catch (\Exception $e) {
            // Log error but don't break the API response
            Log::error('Failed to calculate student final score', [
                'student_id' => $studentId,
                'course_offering_id' => $courseOfferingId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Calculate score for a specific assessment component
     */
    protected function calculateComponentScore($component, int $studentId): ?float
    {
        if ($component->details->isEmpty()) {
            // Component has no details, check if there are direct scores
            return null;
        }

        $totalDetailScore = 0;
        $totalDetailWeight = 0;
        $hasAnyScores = false;

        foreach ($component->details as $detail) {
            $detailWeight = $detail->weight ?? 0;

            if ($detailWeight <= 0) {
                continue;
            }

            // Get the best/latest score for this detail
            $score = $detail->scores->sortByDesc('graded_at')->first();

            if ($score && $score->percentage_score !== null) {
                $totalDetailScore += ($score->percentage_score * $detailWeight);
                $totalDetailWeight += $detailWeight;
                $hasAnyScores = true;
            }
        }

        if (! $hasAnyScores || $totalDetailWeight == 0) {
            return null;
        }

        return $totalDetailScore / $totalDetailWeight;
    }

    /**
     * Get student status based on attendance
     */
    protected function getStudentStatus(array $attendanceStats, float $threshold = 80.00): string
    {
        $percentage = $attendanceStats['percentage'];

        if ($percentage >= 90) {
            return 'excellent';
        }
        if ($percentage >= $threshold) {
            return 'good';
        }
        if ($percentage >= ($threshold - 15)) {
            return 'warning';
        }

        return 'at_risk';
    }

    /**
     * Placeholder methods for detailed statistics
     */
    protected function getDetailedEnrollmentStats(CourseOffering $courseOffering): array
    {
        return $this->getEnrollmentStatistics($courseOffering);
    }

    protected function getDetailedAttendanceStats(CourseOffering $courseOffering): array
    {
        return $this->getAttendanceStatistics($courseOffering);
    }

    protected function getDetailedSessionStats(CourseOffering $courseOffering): array
    {
        return $this->getSessionOverview($courseOffering);
    }

    protected function getPerformanceTrends(CourseOffering $courseOffering): array
    {
        return ['trend' => 'stable', 'change' => 0];
    }

    protected function getWeeklyBreakdown(CourseOffering $courseOffering): array
    {
        return [];
    }
}
