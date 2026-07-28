<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\Attendance;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StudentAttendanceService
{
    /**
     * Get attendance for a specific course
     */
    public function getCourseAttendance(int $studentId, int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with(['unit', 'semester', 'lecture'])
            ->findOrFail($courseOfferingId);

        // Verify student is enrolled
        $isEnrolled = CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('course_offering_id', $courseOfferingId)
            ->where('registration_status', 'registered')
            ->exists();

        if (! $isEnrolled) {
            throw new \Exception('Student is not enrolled in this course');
        }

        $attendanceRecords = Attendance::where('student_id', $studentId)
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
     * Get comprehensive attendance report with semester filtering
     */
    public function getAttendanceReport(int $studentId, ?int $semesterId = null): array
    {
        // Get all semesters the student has enrollments in
        $availableSemesters = $this->getStudentSemesters($studentId);

        if ($availableSemesters->isEmpty()) {
            return $this->getEmptyAttendanceReport();
        }

        // Determine which semester to use for the report
        $targetSemester = $this->determineReportSemester($studentId, $semesterId, $availableSemesters);

        if (! $targetSemester) {
            return $this->getEmptyAttendanceReport();
        }

        // Get report data for the target semester
        $reportData = $this->generateSemesterAttendanceReport($studentId, $targetSemester);

        return [
            'active_semester_id' => $targetSemester->id,
            'semesters' => $this->formatSemesterList($availableSemesters),
            'report' => $reportData,
        ];
    }

    /**
     * Get all semesters where student has enrollments
     */
    protected function getStudentSemesters(int $studentId): Collection
    {
        return Semester::where(function ($query) use ($studentId) {
            $query->whereHas('enrollments', function ($enrollmentQuery) use ($studentId) {
                $enrollmentQuery->where('student_id', $studentId);
            })
                ->orWhereHas('courseRegistrations', function ($registrationQuery) use ($studentId) {
                    $registrationQuery->where('student_id', $studentId)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed']);
                })
                ->orWhereHas('courseOfferings.classSessions.attendances', function ($attendanceQuery) use ($studentId) {
                    $attendanceQuery->where('student_id', $studentId);
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
    protected function determineReportSemester(int $studentId, ?int $requestedSemesterId, Collection $availableSemesters): ?Semester
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
            $hasAttendanceData = Attendance::where('student_id', $studentId)
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
    protected function generateSemesterAttendanceReport(int $studentId, Semester $semester): array
    {
        // Get all course registrations for the semester
        $courseRegistrations = CourseRegistration::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semester->id)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed'])
            ->with([
                'courseOffering.unit',
                'courseOffering.classSessions' => function ($query) {
                    $query->orderBy('session_date', 'asc');
                },
                'courseOffering.classSessions.attendances' => function ($query) use ($studentId) {
                    $query->where('student_id', $studentId);
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
}
