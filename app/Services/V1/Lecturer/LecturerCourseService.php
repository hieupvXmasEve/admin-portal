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
     */
    public function getCourseOfferings(
        Lecture $lecturer,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $lecturer->courseOfferings()
            ->with([
                'curriculumUnit',
                'semester',
                'courseRegistrations' => function ($q) {
                    $q->where('registration_status', 'confirmed');
                },
                'classSessions' => function ($q) {
                    $q->orderBy('session_date', 'desc');
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
        $cacheKey = "lecturer-course-details:{$lecturer->id}:{$courseOfferingId}";

        return Cache::remember($cacheKey, 600, function () use ($lecturer, $courseOfferingId) {
            $courseOffering = $lecturer->courseOfferings()
                ->with([
                    'curriculumUnit',
                    'semester',
                    'courseRegistrations.student',
                    'classSessions' => function ($q) {
                        $q->orderBy('session_date', 'asc');
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
        $courseOffering = $lecturer->courseOfferings()
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->first();

        if (! $courseOffering) {
            return null;
        }

        return $courseOffering->curriculumUnit->unit;
    }

    /**
     * Get course statistics for lecturer
     */
    public function getCourseStatistics(Lecture $lecturer, int $courseOfferingId): array
    {
        $courseOffering = $lecturer->courseOfferings()
            ->with(['courseRegistrations', 'classSessions.attendances'])
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
        $courseOffering = $lecturer->courseOfferings()
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
            ->where('registration_status', 'confirmed');

        // Apply student filters
        $this->applyStudentFilters($studentsQuery, $filters);

        $students = $studentsQuery->get();

        return $students->map(function ($registration) use ($courseOfferingId) {
            $student = $registration->student;
            $attendanceStats = $this->calculateStudentAttendanceStats($student, $courseOfferingId);
            $academicRecord = $student->academicRecords->first();
            $academicStanding = $student->academicStandings->first();
            $finalScore = $this->calculateStudentFinalScore($student->id, $courseOfferingId);

            return [
                'student_id' => $student->id,
                'student_number' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'registration_date' => $registration->created_at->format('Y-m-d'),

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
                'meets_attendance_requirement' => $academicRecord?->meets_attendance_requirement ??
                    ($attendanceStats['percentage'] >= 75),
                'academic_standing' => $academicStanding?->standing ?? 'good',
                'academic_standing_label' => $academicStanding?->standing_label ?? 'Good Standing',

                'status' => $this->getStudentStatus($attendanceStats),
            ];
        })->toArray();
    }

    /**
     * Get filter options for courses
     */
    public function getFilterOptions(Lecture $lecturer): array
    {
        $semesters = $lecturer->courseOfferings()
            ->with('semester')
            ->get()
            ->pluck('semester')
            ->unique('id')
            ->sortByDesc('start_date')
            ->values();

        $deliveryModes = $lecturer->courseOfferings()
            ->distinct()
            ->pluck('delivery_mode')
            ->filter()
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
            $query->whereHas('curriculumUnit.unit', function ($q) use ($search) {
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
        return [
            'id' => $courseOffering->id,
            'section_code' => $courseOffering->section_code,
            'delivery_mode' => $courseOffering->delivery_mode,
            'location' => $courseOffering->location,
            'max_capacity' => $courseOffering->max_capacity,
            'current_enrollment' => $courseOffering->current_enrollment,
            'enrollment_status' => $courseOffering->enrollment_status,
            'schedule_days' => $courseOffering->schedule_days,
            'schedule_time_start' => $courseOffering->schedule_time_start?->format('H:i'),
            'schedule_time_end' => $courseOffering->schedule_time_end?->format('H:i'),
            'curriculum_unit' => [
                'id' => $courseOffering->curriculumUnit->id,
                'unit_code' => $courseOffering->curriculumUnit->unit->code,
                'unit_name' => $courseOffering->curriculumUnit->unit->name,
                'credit_points' => $courseOffering->curriculumUnit->unit->credit_points,
                'description' => $courseOffering->curriculumUnit->note,
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
        $enrolledStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'confirmed')->count();
        $waitlistedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'waitlisted')->count();
        $droppedStudents = $courseOffering->courseRegistrations()
            ->where('registration_status', 'dropped')->count();

        return [
            'total_registrations' => $totalRegistrations,
            'enrolled_students' => $enrolledStudents,
            'waitlisted_students' => $waitlistedStudents,
            'dropped_students' => $droppedStudents,
            'capacity_utilization' => $courseOffering->max_capacity > 0
                ? round(($enrolledStudents / $courseOffering->max_capacity) * 100, 1)
                : 0,
            'available_spots' => max(0, $courseOffering->max_capacity - $enrolledStudents),
        ];
    }

    /**
     * Get attendance statistics for course
     */
    protected function getAttendanceStatistics(CourseOffering $courseOffering): array
    {
        $sessions = $courseOffering->classSessions;
        $totalSessions = $sessions->count();
        $completedSessions = $sessions->where('status', 'completed')->count();
        $sessionsWithAttendance = $sessions->where('attendance_marked', true)->count();

        $attendanceRecords = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
            ->where('attendances.status', 'present')
            ->count();

        $totalPossibleAttendances = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.course_offering_id', $courseOffering->id)
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
        $totalSessions = DB::table('class_sessions')
            ->where('course_offering_id', $courseOfferingId)
            ->where('actual_attendees', '>', 0)
            ->count();

        $attendedSessions = $attendances->whereIn('status', ['present', 'late'])->count();
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
    protected function getStudentStatus(array $attendanceStats): string
    {
        $percentage = $attendanceStats['percentage'];

        if ($percentage >= 90) {
            return 'excellent';
        }
        if ($percentage >= 75) {
            return 'good';
        }
        if ($percentage >= 60) {
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
