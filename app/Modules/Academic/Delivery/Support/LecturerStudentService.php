<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\Student;
use App\Models\StudentNote;
use App\Shared\Contracts\Identity\LecturerTeachingActor as Lecture;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class LecturerStudentService
{
    /**
     * Get students for lecturer's courses with filtering and pagination
     */
    public function getStudents(
        Lecture $lecturer,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Student::whereHas('courseRegistrations', function ($q) use ($lecturer) {
            $q->whereHas('courseOffering', function ($courseQuery) use ($lecturer) {
                $courseQuery->where('lecture_id', $lecturer->id)
                    ->where('is_active', true);
            })->where('registration_status', 'enrolled');
        })->with([
            'courseRegistrations' => function ($q) use ($lecturer) {
                $q->whereHas('courseOffering', function ($courseQuery) use ($lecturer) {
                    $courseQuery->where('lecture_id', $lecturer->id);
                })->with('courseOffering.unit');
            },
            'attendances' => function ($q) use ($lecturer) {
                $q->whereHas('classSession', function ($sessionQuery) use ($lecturer) {
                    $sessionQuery->where('lecture_id', $lecturer->id);
                });
            },
        ]);

        // Apply filters
        $this->applyStudentFilters($query, $filters, $lecturer);

        return $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);
    }

    /**
     * Get detailed student information
     */
    public function getStudentDetails(Lecture $lecturer, int $studentId): ?array
    {
        $student = Student::whereHas('courseRegistrations', function ($q) use ($lecturer) {
            $q->whereHas('courseOffering', function ($courseQuery) use ($lecturer) {
                $courseQuery->where('lecture_id', $lecturer->id);
            });
        })->with([
            'courseRegistrations' => function ($q) use ($lecturer) {
                $q->whereHas('courseOffering', function ($courseQuery) use ($lecturer) {
                    $courseQuery->where('lecture_id', $lecturer->id);
                })->with(['courseOffering.unit', 'courseOffering.semester']);
            },
            'attendances' => function ($q) use ($lecturer) {
                $q->whereHas('classSession', function ($sessionQuery) use ($lecturer) {
                    $sessionQuery->where('lecture_id', $lecturer->id);
                })->with('classSession.courseOffering.unit');
            },
        ])->where('id', $studentId)->first();

        if (! $student) {
            return null;
        }

        return [
            'student' => $this->formatStudentData($student),
            'course_enrollments' => $this->getStudentCourseEnrollments($student, $lecturer),
            'attendance_summary' => $this->getStudentAttendanceSummary($student, $lecturer),
            'performance_indicators' => $this->getStudentPerformanceIndicators($student, $lecturer),
            'alerts' => $this->getStudentAlerts($student, $lecturer),
            'notes' => $this->getStudentNotes($student, $lecturer),
            'recent_activity' => $this->getStudentRecentActivity($student, $lecturer),
        ];
    }

    /**
     * Get student alerts for lecturer
     */
    public function getStudentAlerts(Lecture $lecturer, array $filters = []): array
    {
        $cacheKey = "lecturer-student-alerts:{$lecturer->id}:" . md5(serialize($filters));

        return Cache::remember($cacheKey, 300, function () use ($lecturer, $filters) {
            $alerts = [];

            // Low attendance alerts
            $lowAttendanceStudents = $this->getLowAttendanceStudents($lecturer, $filters);
            foreach ($lowAttendanceStudents as $student) {
                $alerts[] = [
                    'type' => 'low_attendance',
                    'priority' => $student['attendance_percentage'] < 50 ? 'high' : 'medium',
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'course_code' => $student['course_code'],
                    'attendance_percentage' => $student['attendance_percentage'],
                    'message' => "{$student['student_name']} has {$student['attendance_percentage']}% attendance in {$student['course_code']}",
                    'created_at' => now()->toISOString(),
                ];
            }

            // Consecutive absence alerts
            $consecutiveAbsenceStudents = $this->getConsecutiveAbsenceStudents($lecturer, $filters);
            foreach ($consecutiveAbsenceStudents as $student) {
                $alerts[] = [
                    'type' => 'consecutive_absence',
                    'priority' => 'high',
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'course_code' => $student['course_code'],
                    'consecutive_absences' => $student['consecutive_absences'],
                    'message' => "{$student['student_name']} has {$student['consecutive_absences']} consecutive absences in {$student['course_code']}",
                    'created_at' => now()->toISOString(),
                ];
            }

            // No recent attendance alerts
            $noRecentAttendanceStudents = $this->getNoRecentAttendanceStudents($lecturer, $filters);
            foreach ($noRecentAttendanceStudents as $student) {
                $alerts[] = [
                    'type' => 'no_recent_attendance',
                    'priority' => 'medium',
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'course_code' => $student['course_code'],
                    'days_since_attendance' => $student['days_since_attendance'],
                    'message' => "{$student['student_name']} hasn't attended {$student['course_code']} for {$student['days_since_attendance']} days",
                    'created_at' => now()->toISOString(),
                ];
            }

            return collect($alerts)->sortByDesc('priority')->values()->toArray();
        });
    }

    /**
     * Add note for student
     */
    public function addStudentNote(
        Lecture $lecturer,
        int $studentId,
        array $noteData
    ): array {
        // Verify lecturer has access to this student
        $hasAccess = Student::whereHas('courseRegistrations', function ($q) use ($lecturer) {
            $q->whereHas('courseOffering', function ($courseQuery) use ($lecturer) {
                $courseQuery->where('lecture_id', $lecturer->id);
            });
        })->where('id', $studentId)->exists();

        if (! $hasAccess) {
            throw new \Exception('Student not found or access denied');
        }

        $note = StudentNote::create([
            'student_id' => $studentId,
            'lecture_id' => $lecturer->id,
            'course_offering_id' => $noteData['course_offering_id'] ?? null,
            'note_type' => $noteData['note_type'] ?? 'general',
            'title' => $noteData['title'],
            'content' => $noteData['content'],
            'is_private' => $noteData['is_private'] ?? true,
            'is_alert' => $noteData['is_alert'] ?? false,
            'priority' => $noteData['priority'] ?? 'medium',
        ]);

        return [
            'note' => $this->formatNoteData($note),
            'message' => 'Note added successfully',
        ];
    }

    /**
     * Update student note
     */
    public function updateStudentNote(
        Lecture $lecturer,
        int $noteId,
        array $updateData
    ): array {
        $note = StudentNote::where('id', $noteId)
            ->where('lecture_id', $lecturer->id)
            ->first();

        if (! $note) {
            throw new \Exception('Note not found or access denied');
        }

        $note->update($updateData);

        return [
            'note' => $this->formatNoteData($note),
            'message' => 'Note updated successfully',
        ];
    }

    /**
     * Delete student note
     */
    public function deleteStudentNote(Lecture $lecturer, int $noteId): array
    {
        $note = StudentNote::where('id', $noteId)
            ->where('lecture_id', $lecturer->id)
            ->first();

        if (! $note) {
            throw new \Exception('Note not found or access denied');
        }

        $note->delete();

        return [
            'message' => 'Note deleted successfully',
        ];
    }

    /**
     * Get student performance analytics
     */
    public function getStudentPerformanceAnalytics(
        Lecture $lecturer,
        array $filters = []
    ): array {
        $students = $this->getStudents($lecturer, $filters, 1000); // Get all for analytics

        $analytics = [
            'total_students' => $students->total(),
            'attendance_distribution' => $this->getAttendanceDistribution($students),
            'risk_assessment' => $this->getRiskAssessmentSummary($students),
            'course_performance' => $this->getCoursePerformanceSummary($lecturer, $filters),
            'trends' => $this->getPerformanceTrends($lecturer, $filters),
        ];

        return $analytics;
    }

    /**
     * Apply student filters to query
     */
    protected function applyStudentFilters($query, array $filters, Lecture $lecturer): void
    {
        if (! empty($filters['course_offering_id'])) {
            $query->whereHas('courseRegistrations', function ($q) use ($filters) {
                $q->where('course_offering_id', $filters['course_offering_id']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['attendance_status'])) {
            // This would require complex subquery logic
            // Implementation depends on specific requirements
        }

        if (! empty($filters['alert_type'])) {
            // Filter students with specific alert types
            // Implementation depends on alert system design
        }
    }

    /**
     * Format student data
     */
    protected function formatStudentData(Student $student): array
    {
        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'full_name' => $student->full_name,
            'email' => $student->email,
            'phone' => $student->phone,
            'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
            'enrollment_status' => $student->enrollment_status,
            'academic_level' => $student->academic_level,
            'program' => $student->program,
            'year_level' => $student->year_level,
        ];
    }

    /**
     * Get student course enrollments
     */
    protected function getStudentCourseEnrollments(Student $student, Lecture $lecturer): array
    {
        return $student->courseRegistrations->map(function ($registration) {
            return [
                'course_offering_id' => $registration->courseOffering->id,
                'unit_code' => $registration->courseOffering->unit->code,
                'unit_name' => $registration->courseOffering->unit->name,
                'section_code' => $registration->courseOffering->section_code,
                'semester' => $registration->courseOffering->semester->name,
                'registration_date' => $registration->created_at->format('Y-m-d'),
                'registration_status' => $registration->registration_status,
            ];
        })->toArray();
    }

    /**
     * Get student attendance summary
     */
    protected function getStudentAttendanceSummary(Student $student, Lecture $lecturer): array
    {
        $attendances = $student->attendances;
        $totalSessions = $attendances->count();
        $presentSessions = $attendances->whereIn('status', ['present', 'late'])->count();
        $absentSessions = $attendances->where('status', 'absent')->count();
        $excusedSessions = $attendances->where('status', 'excused')->count();

        return [
            'total_sessions' => $totalSessions,
            'present_sessions' => $presentSessions,
            'absent_sessions' => $absentSessions,
            'excused_sessions' => $excusedSessions,
            'attendance_percentage' => $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0,
            'last_attendance' => $attendances->sortByDesc('created_at')->first()?->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Get student performance indicators
     */
    protected function getStudentPerformanceIndicators(Student $student, Lecture $lecturer): array
    {
        $attendanceSummary = $this->getStudentAttendanceSummary($student, $lecturer);

        return [
            'attendance_status' => $this->getAttendanceStatus($attendanceSummary['attendance_percentage']),
            'risk_level' => $this->getRiskLevel($student, $lecturer),
            'engagement_score' => $this->calculateEngagementScore($student, $lecturer),
            'needs_attention' => $this->needsAttention($student, $lecturer),
        ];
    }

    /**
     * Get student notes
     */
    protected function getStudentNotes(Student $student, Lecture $lecturer): array
    {
        $notes = StudentNote::where('student_id', $student->id)
            ->where('lecture_id', $lecturer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $notes->map(function ($note) {
            return $this->formatNoteData($note);
        })->toArray();
    }

    /**
     * Format note data
     */
    protected function formatNoteData(StudentNote $note): array
    {
        return [
            'id' => $note->id,
            'note_type' => $note->note_type,
            'title' => $note->title,
            'content' => $note->content,
            'is_private' => $note->is_private,
            'is_alert' => $note->is_alert,
            'priority' => $note->priority,
            'created_at' => $note->created_at->toISOString(),
            'updated_at' => $note->updated_at->toISOString(),
        ];
    }

    /**
     * Get student recent activity
     */
    protected function getStudentRecentActivity(Student $student, Lecture $lecturer): array
    {
        // This would typically come from an activity log
        // For now, return recent attendance records
        $recentAttendances = $student->attendances()
            ->whereHas('classSession', function ($q) use ($lecturer) {
                $q->where('lecture_id', $lecturer->id);
            })
            ->with('classSession.courseOffering.unit')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return $recentAttendances->map(function ($attendance) {
            return [
                'type' => 'attendance',
                'status' => $attendance->status,
                'course' => $attendance->classSession->courseOffering->unit->code,
                'date' => $attendance->classSession->session_date->format('Y-m-d'),
                'time' => $attendance->created_at->format('H:i'),
            ];
        })->toArray();
    }

    /**
     * Placeholder methods for complex analytics
     */
    protected function getLowAttendanceStudents(Lecture $lecturer, array $filters): array
    {
        // Implementation would identify students with attendance below threshold
        return [];
    }

    protected function getConsecutiveAbsenceStudents(Lecture $lecturer, array $filters): array
    {
        // Implementation would identify students with consecutive absences
        return [];
    }

    protected function getNoRecentAttendanceStudents(Lecture $lecturer, array $filters): array
    {
        // Implementation would identify students with no recent attendance
        return [];
    }

    protected function getAttendanceDistribution($students): array
    {
        // Implementation would calculate attendance distribution
        return [];
    }

    protected function getRiskAssessmentSummary($students): array
    {
        // Implementation would provide risk assessment summary
        return [];
    }

    protected function getCoursePerformanceSummary(Lecture $lecturer, array $filters): array
    {
        // Implementation would provide course performance summary
        return [];
    }

    protected function getPerformanceTrends(Lecture $lecturer, array $filters): array
    {
        // Implementation would calculate performance trends
        return [];
    }

    protected function getAttendanceStatus(float $percentage): string
    {
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

    protected function getRiskLevel(Student $student, Lecture $lecturer): string
    {
        // Implementation would calculate risk level based on multiple factors
        return 'low';
    }

    protected function calculateEngagementScore(Student $student, Lecture $lecturer): float
    {
        // Implementation would calculate engagement score
        return 75.0;
    }

    protected function needsAttention(Student $student, Lecture $lecturer): bool
    {
        // Implementation would determine if student needs attention
        return false;
    }
}
