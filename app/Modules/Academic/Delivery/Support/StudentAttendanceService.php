<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\Attendance;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StudentAttendanceService
{
    /**
     * Get student's attendance summary
     */
    public function getAttendanceSummary(Student $student, ?int $semesterId = null, array $filters = []): array
    {
        $semester = $this->resolveSemester($semesterId);

        if (! $semester) {
            return $this->getEmptyAttendanceSummary();
        }

        $cacheKey = "attendance:summary:student:{$student->id}:semester:{$semester->id}:".md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($student, $semester, $filters) {
            $attendanceRecords = $this->getAttendanceRecords($student, $semester, $filters);

            return [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                ],
                'overall_summary' => $this->calculateOverallSummary($attendanceRecords),
                'attendance_by_course' => $this->groupAttendanceByCourse($attendanceRecords),
                'attendance_trends' => $this->calculateAttendanceTrends($student, $semester),
                'recent_attendance' => $this->getRecentAttendance($attendanceRecords, 10),
                'attendance_alerts' => $this->generateAttendanceAlerts($student, $semester),
            ];
        });
    }

    /**
     * Get attendance for a specific course
     */
    public function getCourseAttendance(Student $student, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with(['unit', 'semester', 'lecture'])
            ->findOrFail($courseOfferingId);

        // Verify student is enrolled
        $isEnrolled = $student->courseRegistrations()
            ->where('course_offering_id', $courseOfferingId)
            ->where('registration_status', 'registered')
            ->exists();

        if (! $isEnrolled) {
            throw new \Exception('Student is not enrolled in this course');
        }

        $attendanceRecords = Attendance::where('student_id', $student->id)
            ->where('course_offering_id', $courseOfferingId)
            ->with(['classSession'])
            ->orderBy('session_date', 'desc')
            ->get();

        return [
            'course_info' => [
                'id' => $courseOffering->id,
                'code' => $courseOffering->unit->code,
                'name' => $courseOffering->unit->name,
                'semester' => $courseOffering->semester->name,
                'lecturer' => $courseOffering->lecturer?->full_name,
            ],
            'attendance_summary' => $this->calculateCourseAttendanceSummary($attendanceRecords),
            'attendance_records' => $this->formatAttendanceRecords($attendanceRecords),
            'attendance_pattern' => $this->analyzeAttendancePattern($attendanceRecords),
            'upcoming_sessions' => $this->getUpcomingSessions($courseOffering),
        ];
    }

    /**
     * Get attendance statistics
     */
    public function getAttendanceStatistics(Student $student, ?int $semesterId = null): array
    {
        $semester = $this->resolveSemester($semesterId);

        if (! $semester) {
            return [];
        }

        $attendanceRecords = $this->getAttendanceRecords($student, $semester);

        return [
            'overall_statistics' => $this->calculateOverallStatistics($attendanceRecords),
            'monthly_breakdown' => $this->calculateMonthlyBreakdown($attendanceRecords),
            'day_of_week_analysis' => $this->analyzeDayOfWeekAttendance($attendanceRecords),
            'time_of_day_analysis' => $this->analyzeTimeOfDayAttendance($attendanceRecords),
            'course_comparison' => $this->compareCourseAttendance($attendanceRecords),
        ];
    }

    /**
     * Get attendance records with filters
     */
    protected function getAttendanceRecords(Student $student, Semester $semester, array $filters = []): Collection
    {
        $query = Attendance::where('student_id', $student->id)
            ->whereHas('courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            })
            ->with(['courseOffering.unit', 'classSession']);

        // Apply filters
        $this->applyAttendanceFilters($query, $filters);

        return $query->orderBy('session_date', 'desc')->get();
    }

    /**
     * Apply attendance filters
     */
    protected function applyAttendanceFilters($query, array $filters): void
    {
        if (! empty($filters['course_code'])) {
            $query->whereHas('courseOffering.unit', function ($q) use ($filters) {
                $q->where('code', 'like', '%'.$filters['course_code'].'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('session_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('session_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['day_of_week'])) {
            $query->whereHas('classSession', function ($q) use ($filters) {
                $q->where('day_of_week', $filters['day_of_week']);
            });
        }
    }

    /**
     * Calculate overall summary
     */
    protected function calculateOverallSummary(Collection $attendanceRecords): array
    {
        $totalSessions = $attendanceRecords->count();
        $presentSessions = $attendanceRecords->whereIn('status', ['present', 'late'])->count();
        $absentSessions = $attendanceRecords->where('status', 'absent')->count();
        $lateSessions = $attendanceRecords->where('status', 'late')->count();
        $excusedSessions = $attendanceRecords->where('status', 'excused')->count();

        $attendanceRate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0;

        return [
            'total_sessions' => $totalSessions,
            'present_sessions' => $presentSessions,
            'absent_sessions' => $absentSessions,
            'late_sessions' => $lateSessions,
            'excused_sessions' => $excusedSessions,
            'attendance_rate' => $attendanceRate,
            'attendance_status' => $this->getAttendanceStatus($attendanceRate),
            'punctuality_rate' => $totalSessions > 0
                ? round((($presentSessions - $lateSessions) / $totalSessions) * 100, 1)
                : 0,
        ];
    }

    /**
     * Group attendance by course
     */
    protected function groupAttendanceByCourse(Collection $attendanceRecords): array
    {
        return $attendanceRecords->groupBy('course_offering_id')->map(function ($records, $courseOfferingId) {
            $courseOffering = $records->first()->courseOffering;
            $summary = $this->calculateCourseAttendanceSummary($records);

            return [
                'course_offering_id' => $courseOfferingId,
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'attendance_summary' => $summary,
                'recent_sessions' => $records->take(5)->map(function ($record) {
                    return [
                        'date' => $record->session_date->toDateString(),
                        'status' => $record->status,
                        'status_display' => $this->getStatusDisplay($record->status),
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Calculate course attendance summary
     */
    protected function calculateCourseAttendanceSummary(Collection $records): array
    {
        $totalSessions = $records->count();
        $presentSessions = $records->whereIn('status', ['present', 'late'])->count();
        $attendanceRate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0;

        return [
            'total_sessions' => $totalSessions,
            'present_sessions' => $presentSessions,
            'absent_sessions' => $records->where('status', 'absent')->count(),
            'late_sessions' => $records->where('status', 'late')->count(),
            'excused_sessions' => $records->where('status', 'excused')->count(),
            'attendance_rate' => $attendanceRate,
            'attendance_status' => $this->getAttendanceStatus($attendanceRate),
            'meets_requirement' => $this->meetsAttendanceRequirement($attendanceRate),
        ];
    }

    /**
     * Calculate attendance trends
     */
    protected function calculateAttendanceTrends(Student $student, Semester $semester): array
    {
        $weeklyAttendance = Attendance::where('student_id', $student->id)
            ->whereHas('courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            })
            ->selectRaw('WEEK(session_date) as week, COUNT(*) as total_sessions,
                       SUM(CASE WHEN status IN ("present", "late") THEN 1 ELSE 0 END) as present_sessions')
//            ->groupBy('week')
            ->orderBy('week')
            ->get();

        $trendData = $weeklyAttendance->map(function ($week) {
            $rate = $week->total_sessions > 0
                ? round(($week->present_sessions / $week->total_sessions) * 100, 1)
                : 0;

            return [
                'week' => $week->week,
                'total_sessions' => $week->total_sessions,
                'present_sessions' => $week->present_sessions,
                'attendance_rate' => $rate,
            ];
        });

        return [
            'weekly_trends' => $trendData->toArray(),
            'trend_analysis' => $this->analyzeTrend($trendData),
        ];
    }

    /**
     * Get recent attendance
     */
    protected function getRecentAttendance(Collection $attendanceRecords, int $limit): array
    {
        return $attendanceRecords->take($limit)->map(function ($record) {
            return [
                'id' => $record->id,
                'course_code' => $record->courseOffering->unit->code,
                'course_name' => $record->courseOffering->unit->name,
                'session_date' => $record->session_date->toDateString(),
                'session_time' => $record->classSession ?
                    $record->classSession->start_time.' - '.$record->classSession->end_time : null,
                'status' => $record->status,
                'status_display' => $this->getStatusDisplay($record->status),
                'marked_at' => $record->marked_at?->toDateTimeString(),
                'notes' => $record->notes,
            ];
        })->toArray();
    }

    /**
     * Generate attendance alerts
     */
    protected function generateAttendanceAlerts(Student $student, Semester $semester): array
    {
        $alerts = [];

        // Get courses with low attendance
        $courseAttendance = $this->groupAttendanceByCourse(
            $this->getAttendanceRecords($student, $semester)
        );

        foreach ($courseAttendance as $course) {
            $rate = $course['attendance_summary']['attendance_rate'];

            if ($rate < 75) {
                $alerts[] = [
                    'type' => 'low_attendance',
                    'severity' => $rate < 60 ? 'critical' : 'warning',
                    'course_code' => $course['course_code'],
                    'course_name' => $course['course_name'],
                    'attendance_rate' => $rate,
                    'message' => "Attendance rate of {$rate}% is below the required threshold",
                    'action_required' => $rate < 60,
                ];
            }
        }

        // Check for consecutive absences
        $recentAbsences = Attendance::where('student_id', $student->id)
            ->whereHas('courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            })
            ->where('status', 'absent')
            ->where('session_date', '>=', now()->subDays(14))
            ->count();

        if ($recentAbsences >= 3) {
            $alerts[] = [
                'type' => 'consecutive_absences',
                'severity' => 'warning',
                'message' => "You have {$recentAbsences} absences in the last 2 weeks",
                'action_required' => true,
            ];
        }

        return $alerts;
    }

    /**
     * Format attendance records
     */
    protected function formatAttendanceRecords(Collection $records): array
    {
        return $records->map(function ($record) {
            return [
                'id' => $record->id,
                'session_date' => $record->session_date->toDateString(),
                'session_time' => $record->classSession ? [
                    'start' => $record->classSession->start_time,
                    'end' => $record->classSession->end_time,
                    'display' => $this->formatTimeRange(
                        $record->classSession->start_time,
                        $record->classSession->end_time
                    ),
                ] : null,
                'status' => $record->status,
                'status_display' => $this->getStatusDisplay($record->status),
                'marked_at' => $record->marked_at?->toDateTimeString(),
                'marked_by' => $record->marked_by,
                'notes' => $record->notes,
                'is_late' => $record->status === 'late',
                'is_excused' => $record->status === 'excused',
            ];
        })->toArray();
    }

    /**
     * Analyze attendance pattern
     */
    protected function analyzeAttendancePattern(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [
                'pattern_type' => 'no_data',
                'consistency' => 'unknown',
                'risk_level' => 'unknown',
            ];
        }

        $attendanceRate = $records->whereIn('status', ['present', 'late'])->count() / $records->count() * 100;
        $recentRate = $records->take(10)->whereIn('status', ['present', 'late'])->count() / min(10, $records->count()) * 100;

        $patternType = $this->determinePatternType($records);
        $consistency = $this->calculateConsistency($records);
        $riskLevel = $this->assessRiskLevel($attendanceRate, $recentRate, $consistency);

        return [
            'pattern_type' => $patternType,
            'consistency' => $consistency,
            'risk_level' => $riskLevel,
            'overall_rate' => round($attendanceRate, 1),
            'recent_rate' => round($recentRate, 1),
            'trend' => $recentRate > $attendanceRate ? 'improving' : ($recentRate < $attendanceRate ? 'declining' : 'stable'),
        ];
    }

    /**
     * Get upcoming sessions
     */
    protected function getUpcomingSessions(CourseOffering $courseOffering): array
    {
        // This would return upcoming class sessions for the course
        // Implementation depends on how you track scheduled sessions
        return [];
    }

    /**
     * Calculate overall statistics
     */
    protected function calculateOverallStatistics(Collection $records): array
    {
        $totalSessions = $records->count();
        $presentSessions = $records->whereIn('status', ['present', 'late'])->count();

        return [
            'total_sessions' => $totalSessions,
            'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            'most_attended_course' => $this->getMostAttendedCourse($records),
            'least_attended_course' => $this->getLeastAttendedCourse($records),
            'perfect_attendance_courses' => $this->getPerfectAttendanceCourses($records),
        ];
    }

    /**
     * Calculate monthly breakdown
     */
    protected function calculateMonthlyBreakdown(Collection $records): array
    {
        return $records->groupBy(function ($record) {
            return $record->session_date->format('Y-m');
        })->map(function ($monthRecords, $month) {
            $totalSessions = $monthRecords->count();
            $presentSessions = $monthRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'month' => $month,
                'month_display' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'total_sessions' => $totalSessions,
                'present_sessions' => $presentSessions,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Analyze day of week attendance
     */
    protected function analyzeDayOfWeekAttendance(Collection $records): array
    {
        return $records->groupBy(function ($record) {
            return $record->session_date->format('l'); // Full day name
        })->map(function ($dayRecords, $day) {
            $totalSessions = $dayRecords->count();
            $presentSessions = $dayRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'day' => strtolower($day),
                'day_display' => $day,
                'total_sessions' => $totalSessions,
                'present_sessions' => $presentSessions,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Analyze time of day attendance
     */
    protected function analyzeTimeOfDayAttendance(Collection $records): array
    {
        return $records->filter(function ($record) {
            return $record->classSession;
        })->groupBy(function ($record) {
            $hour = Carbon::createFromTimeString($record->classSession->start_time)->hour;

            if ($hour < 12) {
                return 'morning';
            } elseif ($hour < 17) {
                return 'afternoon';
            } else {
                return 'evening';
            }
        })->map(function ($timeRecords, $timeOfDay) {
            $totalSessions = $timeRecords->count();
            $presentSessions = $timeRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'time_of_day' => $timeOfDay,
                'total_sessions' => $totalSessions,
                'present_sessions' => $presentSessions,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        })->toArray();
    }

    /**
     * Compare course attendance
     */
    protected function compareCourseAttendance(Collection $records): array
    {
        return $records->groupBy('course_offering_id')->map(function ($courseRecords) {
            $courseOffering = $courseRecords->first()->courseOffering;
            $totalSessions = $courseRecords->count();
            $presentSessions = $courseRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'total_sessions' => $totalSessions,
                'present_sessions' => $presentSessions,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        })->sortByDesc('attendance_rate')->values()->toArray();
    }

    /**
     * Get attendance status based on rate
     */
    protected function getAttendanceStatus(float $rate): string
    {
        return match (true) {
            $rate >= 95 => 'excellent',
            $rate >= 85 => 'good',
            $rate >= 75 => 'satisfactory',
            $rate >= 60 => 'warning',
            default => 'critical',
        };
    }

    /**
     * Get status display text
     */
    protected function getStatusDisplay(string $status): string
    {
        return match ($status) {
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'excused' => 'Excused',
            default => ucfirst($status),
        };
    }

    /**
     * Check if attendance meets requirement
     */
    protected function meetsAttendanceRequirement(float $rate): bool
    {
        return $rate >= 75; // Assuming 75% is the minimum requirement
    }

    /**
     * Format time range
     */
    protected function formatTimeRange(string $startTime, string $endTime): string
    {
        $start = Carbon::createFromTimeString($startTime);
        $end = Carbon::createFromTimeString($endTime);

        return $start->format('g:i A').' - '.$end->format('g:i A');
    }

    /**
     * Analyze trend from weekly data
     */
    protected function analyzeTrend(Collection $weeklyData): string
    {
        if ($weeklyData->count() < 3) {
            return 'insufficient_data';
        }

        $rates = $weeklyData->pluck('attendance_rate');
        $firstHalf = $rates->take(ceil($rates->count() / 2))->avg();
        $secondHalf = $rates->skip(floor($rates->count() / 2))->avg();

        $change = $secondHalf - $firstHalf;

        return match (true) {
            $change > 5 => 'improving',
            $change < -5 => 'declining',
            default => 'stable',
        };
    }

    /**
     * Determine pattern type
     */
    protected function determinePatternType(Collection $records): string
    {
        $consecutiveAbsences = 0;
        $maxConsecutiveAbsences = 0;
        $totalAbsences = 0;

        foreach ($records->sortBy('session_date') as $record) {
            if ($record->status === 'absent') {
                $consecutiveAbsences++;
                $totalAbsences++;
                $maxConsecutiveAbsences = max($maxConsecutiveAbsences, $consecutiveAbsences);
            } else {
                $consecutiveAbsences = 0;
            }
        }

        $absenceRate = $records->count() > 0 ? ($totalAbsences / $records->count()) * 100 : 0;

        if ($maxConsecutiveAbsences >= 3) {
            return 'sporadic_absences';
        } elseif ($absenceRate > 25) {
            return 'frequent_absences';
        } elseif ($absenceRate < 5) {
            return 'excellent_attendance';
        } else {
            return 'regular_attendance';
        }
    }

    /**
     * Calculate consistency
     */
    protected function calculateConsistency(Collection $records): string
    {
        if ($records->count() < 5) {
            return 'insufficient_data';
        }

        $weeklyAttendance = $records->groupBy(function ($record) {
            return $record->session_date->format('Y-W');
        })->map(function ($weekRecords) {
            return $weekRecords->whereIn('status', ['present', 'late'])->count() / $weekRecords->count() * 100;
        });

        $stdDev = $this->calculateStandardDeviation($weeklyAttendance);

        return match (true) {
            $stdDev < 10 => 'very_consistent',
            $stdDev < 20 => 'consistent',
            $stdDev < 30 => 'moderate',
            default => 'inconsistent',
        };
    }

    /**
     * Assess risk level
     */
    protected function assessRiskLevel(float $overallRate, float $recentRate, string $consistency): string
    {
        if ($overallRate < 60 || $recentRate < 50) {
            return 'high';
        } elseif ($overallRate < 75 || $recentRate < 70 || $consistency === 'inconsistent') {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate standard deviation
     */
    protected function calculateStandardDeviation(Collection $values): float
    {
        $mean = $values->avg();
        $variance = $values->map(fn ($value) => pow($value - $mean, 2))->avg();

        return sqrt($variance);
    }

    /**
     * Get most attended course
     */
    protected function getMostAttendedCourse(Collection $records): ?array
    {
        $courseAttendance = $records->groupBy('course_offering_id')->map(function ($courseRecords) {
            $courseOffering = $courseRecords->first()->courseOffering;
            $totalSessions = $courseRecords->count();
            $presentSessions = $courseRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        });

        return $courseAttendance->sortByDesc('attendance_rate')->first();
    }

    /**
     * Get least attended course
     */
    protected function getLeastAttendedCourse(Collection $records): ?array
    {
        $courseAttendance = $records->groupBy('course_offering_id')->map(function ($courseRecords) {
            $courseOffering = $courseRecords->first()->courseOffering;
            $totalSessions = $courseRecords->count();
            $presentSessions = $courseRecords->whereIn('status', ['present', 'late'])->count();

            return [
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'attendance_rate' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            ];
        });

        return $courseAttendance->sortBy('attendance_rate')->first();
    }

    /**
     * Get courses with perfect attendance
     */
    protected function getPerfectAttendanceCourses(Collection $records): array
    {
        return $records->groupBy('course_offering_id')->filter(function ($courseRecords) {
            return $courseRecords->every(fn ($record) => in_array($record->status, ['present', 'late']));
        })->map(function ($courseRecords) {
            $courseOffering = $courseRecords->first()->courseOffering;

            return [
                'course_code' => $courseOffering->unit->code,
                'course_name' => $courseOffering->unit->name,
                'total_sessions' => $courseRecords->count(),
            ];
        })->values()->toArray();
    }

    /**
     * Resolve semester from ID or get current active semester
     */
    protected function resolveSemester(?int $semesterId): ?Semester
    {
        if ($semesterId) {
            return Semester::find($semesterId);
        }

        $currentPeriodId = app(AcademicPeriodReader::class)->current()?->id;

        return $currentPeriodId === null ? null : Semester::find($currentPeriodId);
    }

    /**
     * Get comprehensive attendance report with semester filtering
     */
    public function getAttendanceReport(Student $student, ?int $semesterId = null): array
    {
        // Get all semesters the student has enrollments in
        $availableSemesters = $this->getStudentSemesters($student);

        if ($availableSemesters->isEmpty()) {
            return $this->getEmptyAttendanceReport();
        }

        // Determine which semester to use for the report
        $targetSemester = $this->determineReportSemester($student, $semesterId, $availableSemesters);

        if (! $targetSemester) {
            return $this->getEmptyAttendanceReport();
        }

        // Get report data for the target semester
        $reportData = $this->generateSemesterAttendanceReport($student, $targetSemester);

        return [
            'active_semester_id' => $targetSemester->id,
            'semesters' => $this->formatSemesterList($availableSemesters),
            'report' => $reportData,
        ];
    }

    /**
     * Get all semesters where student has enrollments
     */
    protected function getStudentSemesters(Student $student): Collection
    {
        return Semester::where(function ($query) use ($student) {
            $query->whereHas('enrollments', function ($enrollmentQuery) use ($student) {
                $enrollmentQuery->where('student_id', $student->id);
            })
                ->orWhereHas('courseRegistrations', function ($registrationQuery) use ($student) {
                    $registrationQuery->where('student_id', $student->id)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed']);
                })
                ->orWhereHas('courseOfferings.classSessions.attendances', function ($attendanceQuery) use ($student) {
                    $attendanceQuery->where('student_id', $student->id);
                });
        })
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Determine which semester to use for the report based on priority:
     * 1. Requested semester (if provided)
     * 2. Current active semester (if student has enrollment)
     * 3. Most recent semester with attendance data
     * 4. Most recent semester with enrollment
     */
    protected function determineReportSemester(Student $student, ?int $requestedSemesterId, Collection $availableSemesters): ?Semester
    {
        // If specific semester requested, use it if student has enrollment
        if ($requestedSemesterId) {
            $requestedSemester = $availableSemesters->firstWhere('id', $requestedSemesterId);
            if ($requestedSemester instanceof Semester) {
                return $requestedSemester;
            }
        }

        // Try to use current active semester if student has enrollment
        $activeSemester = $availableSemesters->firstWhere('is_active', true);
        if ($activeSemester instanceof Semester) {
            return $activeSemester;
        }

        // Find most recent semester with attendance data
        foreach ($availableSemesters as $semester) {
            $hasAttendanceData = Attendance::where('student_id', $student->id)
                ->whereHas('classSession.courseOffering', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->exists();

            if ($hasAttendanceData) {
                return $semester;
            }
        }

        // Fallback to most recent semester with enrollment
        $firstSemester = $availableSemesters->first();

        return $firstSemester instanceof Semester ? $firstSemester : null;
    }

    /**
     * Format semester list for response
     */
    protected function formatSemesterList(Collection $semesters): array
    {
        return $semesters->map(function (Semester $semester) {
            return [
                'id' => $semester->id,
                'name' => $semester->name,
                'is_active' => $semester->is_active,
                'start_date' => $semester->start_date->format('Y-m-d'),
                'end_date' => $semester->end_date->format('Y-m-d'),
            ];
        })->toArray();
    }

    /**
     * Generate attendance report for a specific semester
     */
    protected function generateSemesterAttendanceReport(Student $student, Semester $semester): array
    {
        // Get all course registrations for the semester
        $courseRegistrations = $student->courseRegistrations()
            ->where('semester_id', $semester->id)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed'])
            ->with([
                'courseOffering.unit',
                'courseOffering.classSessions' => function ($query) {
                    $query->orderBy('session_date', 'asc');
                },
                'courseOffering.classSessions.attendances' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                },
            ])
            ->get();

        if ($courseRegistrations->isEmpty()) {
            return $this->getEmptyReportData();
        }

        $subjects = [];
        $totalClasses = 0;
        $totalAttended = 0;
        $totalAbsent = 0;

        foreach ($courseRegistrations as $registration) {
            $courseOffering = $registration->courseOffering;
            $unit = $courseOffering->unit;
            $classSessions = $courseOffering->classSessions;

            $subjectData = [
                'unit_code' => $unit->code,
                'unit_name' => $unit->name,
                'section_code' => $courseOffering->section_code,
                'total_sessions' => $classSessions->count(),
                'attended' => 0,
                'absent' => 0,
                'attendance_rate' => 0,
                'sessions' => [],
            ];

            foreach ($classSessions as $session) {
                $attendance = $session->attendances->first(); // Since we filtered by student_id

                $sessionData = [
                    'id' => $session->id,
                    'date' => $session->session_date->format('Y-m-d'),
                    'session_type' => $session->session_type ?? 'lecture',
                    'status' => null,
                    'status_label' => 'Not Marked',
                    'created_at' => null,
                    'recorded_by_lecturer' => null,
                    'notes' => null,
                ];

                if ($attendance) {
                    $sessionData['status'] = $attendance->status;
                    $sessionData['status_label'] = $this->getStatusDisplay($attendance->status);
                    $sessionData['created_at'] = $attendance->created_at->format('H:i');
                    $sessionData['recorded_by_lecturer'] = $attendance->recordedBy?->name ?? 'System';
                    $sessionData['notes'] = $attendance->notes;

                    if (in_array($attendance->status, ['present', 'late'])) {
                        $subjectData['attended']++;
                        $totalAttended++;
                    } else {
                        $subjectData['absent']++;
                        $totalAbsent++;
                    }
                }

                $subjectData['sessions'][] = $sessionData;
                $totalClasses++;
            }

            // Calculate attendance rate for this subject
            if ($subjectData['total_sessions'] > 0) {
                $subjectData['attendance_rate'] = round(
                    ($subjectData['attended'] / $subjectData['total_sessions']) * 100, 1
                );
            }

            $subjects[] = $subjectData;
        }

        // Calculate overall summary
        $overallAttendanceRate = $totalClasses > 0 ? round(($totalAttended / $totalClasses) * 100, 1) : 0;

        return [
            'summary' => [
                'total_classes' => $totalClasses,
                'attended' => $totalAttended,
                'absent' => $totalAbsent,
                'attendance_rate' => $overallAttendanceRate,
            ],
            'subjects' => $subjects,
        ];
    }

    /**
     * Get empty report data structure
     */
    protected function getEmptyReportData(): array
    {
        return [
            'summary' => [
                'total_classes' => 0,
                'attended' => 0,
                'absent' => 0,
                'attendance_rate' => 0,
            ],
            'subjects' => [],
        ];
    }

    /**
     * Get empty attendance report
     */
    protected function getEmptyAttendanceReport(): array
    {
        return [
            'active_semester_id' => null,
            'semesters' => [],
            'report' => $this->getEmptyReportData(),
        ];
    }

    /**
     * Get empty attendance summary
     */
    protected function getEmptyAttendanceSummary(): array
    {
        return [
            'semester' => null,
            'overall_summary' => [
                'total_sessions' => 0,
                'present_sessions' => 0,
                'absent_sessions' => 0,
                'attendance_rate' => 0,
                'attendance_status' => 'no_data',
            ],
            'attendance_by_course' => [],
            'attendance_trends' => [],
            'recent_attendance' => [],
            'attendance_alerts' => [],
        ];
    }
}
