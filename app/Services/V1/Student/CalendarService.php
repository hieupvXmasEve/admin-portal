<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Student;
use App\Models\Semester;
use App\Models\AcademicCalendarEvent;
use App\Models\AssessmentComponentDetailScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CalendarService
{
    /**
     * Get all semesters for student
     */
    public function getSemesters(Student $student): array
    {
        $cacheKey = "calendar:semesters:student:{$student->id}";

        return Cache::remember($cacheKey, 3600, function () use ($student) {
            $semesters = Semester::where('campus_id', $student->campus_id)
                ->orderBy('start_date', 'desc')
                ->get();

            return [
                'current_semester' => $this->getCurrentSemester($semesters),
                'upcoming_semesters' => $this->getUpcomingSemesters($semesters),
                'past_semesters' => $this->getPastSemesters($semesters),
                'all_semesters' => $this->formatSemesters($semesters),
            ];
        });
    }

    /**
     * Get semester deadlines
     */
    public function getSemesterDeadlines(Student $student, Semester $semester): array
    {
        $cacheKey = "calendar:deadlines:student:{$student->id}:semester:{$semester->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student, $semester) {
            return [
                'semester_info' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                    'start_date' => $semester->start_date->toDateString(),
                    'end_date' => $semester->end_date->toDateString(),
                ],
                'academic_deadlines' => $this->getAcademicDeadlines($semester),
                'assessment_deadlines' => $this->getAssessmentDeadlines($student, $semester),
                'registration_deadlines' => $this->getRegistrationDeadlines($semester),
                'important_dates' => $this->getImportantDates($semester),
                'upcoming_deadlines' => $this->getUpcomingDeadlines($student, $semester),
            ];
        });
    }

    /**
     * Get academic calendar
     */
    public function getAcademicCalendar(Student $student): array
    {
        $cacheKey = "calendar:academic:student:{$student->id}";

        return Cache::remember($cacheKey, 3600, function () use ($student) {
            $currentYear = now()->year;
            $events = AcademicCalendarEvent::where('campus_id', $student->campus_id)
                ->whereYear('event_date', $currentYear)
                ->orderBy('event_date')
                ->get();

            return [
                'academic_year' => $currentYear,
                'events_by_month' => $this->groupEventsByMonth($events),
                'upcoming_events' => $this->getUpcomingEvents($events),
                'event_categories' => $this->getEventCategories($events),
                'holidays' => $this->getHolidays($events),
                'exam_periods' => $this->getExamPeriods($events),
            ];
        });
    }

    /**
     * Get current semester information
     */
    public function getCurrentSemesterInfo(Student $student): array
    {
        $cacheKey = "calendar:current_semester:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $currentSemester = $this->getCurrentSemesterForStudent($student);

            if (!$currentSemester) {
                return [
                    'current_semester' => null,
                    'message' => 'No active semester found',
                ];
            }

            return [
                'current_semester' => [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'code' => $currentSemester->code,
                    'start_date' => $currentSemester->start_date->toDateString(),
                    'end_date' => $currentSemester->end_date->toDateString(),
                    'is_active' => $currentSemester->is_active,
                ],
                'semester_progress' => $this->calculateSemesterProgress($currentSemester),
                'key_dates' => $this->getKeySemesterDates($currentSemester),
                'enrollment_info' => $this->getEnrollmentInfo($student, $currentSemester),
                'upcoming_milestones' => $this->getUpcomingMilestones($currentSemester),
            ];
        });
    }

    /**
     * Get current semester for student
     */
    protected function getCurrentSemesterForStudent(Student $student): ?Semester
    {
        return Semester::where('campus_id', $student->campus_id)
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Get current semester from collection
     */
    protected function getCurrentSemester(Collection $semesters): ?array
    {
        $current = $semesters->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        return $current ? $this->formatSemester($current) : null;
    }

    /**
     * Get upcoming semesters
     */
    protected function getUpcomingSemesters(Collection $semesters): array
    {
        return $semesters->where('start_date', '>', now())
            ->take(3)
            ->map(fn($semester) => $this->formatSemester($semester))
            ->values()
            ->toArray();
    }

    /**
     * Get past semesters
     */
    protected function getPastSemesters(Collection $semesters): array
    {
        return $semesters->where('end_date', '<', now())
            ->take(5)
            ->map(fn($semester) => $this->formatSemester($semester))
            ->values()
            ->toArray();
    }

    /**
     * Format semesters
     */
    protected function formatSemesters(Collection $semesters): array
    {
        return $semesters->map(fn($semester) => $this->formatSemester($semester))->toArray();
    }

    /**
     * Format single semester
     */
    protected function formatSemester(Semester $semester): array
    {
        $now = now();
        $startDate = $semester->start_date;
        $endDate = $semester->end_date;

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'code' => $semester->code,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_active' => $semester->is_active,
            'status' => $this->getSemesterStatus($semester),
            'duration_weeks' => $startDate->diffInWeeks($endDate),
            'days_until_start' => $startDate > $now ? $now->diffInDays($startDate) : 0,
            'days_until_end' => $endDate > $now ? $now->diffInDays($endDate) : 0,
            'registration_period' => [
                'start' => $semester->registration_start_date?->toDateString(),
                'end' => $semester->registration_end_date?->toDateString(),
                'is_open' => $this->isRegistrationOpen($semester),
            ],
        ];
    }

    /**
     * Get academic deadlines
     */
    protected function getAcademicDeadlines(Semester $semester): array
    {
        return [
            'add_drop_deadline' => $semester->add_drop_deadline?->toDateString(),
            'withdrawal_deadline' => $semester->withdrawal_deadline?->toDateString(),
            'final_exam_start' => $semester->final_exam_start_date?->toDateString(),
            'final_exam_end' => $semester->final_exam_end_date?->toDateString(),
            'grades_due' => $semester->grades_due_date?->toDateString(),
        ];
    }

    /**
     * Get assessment deadlines for student
     */
    protected function getAssessmentDeadlines(Student $student, Semester $semester): array
    {
        $assessments = AssessmentComponentDetailScore::where('student_code', $student->id)
            ->whereHas('courseOffering', function ($query) use ($semester) {
                $query->where('semester_id', $semester->id);
            })
            ->whereNotNull('due_date')
            ->with(['assessmentComponentDetail.assessmentComponent', 'courseOffering.curriculumUnit.unit'])
            ->orderBy('due_date')
            ->get();

        return $assessments->map(function ($assessment) {
            return [
                'id' => $assessment->id,
                'title' => $assessment->assessmentComponentDetail->name,
                'course_code' => $assessment->courseOffering->curriculumUnit->unit->code,
                'course_name' => $assessment->courseOffering->curriculumUnit->unit->name,
                'due_date' => $assessment->due_date->toDateString(),
                'due_time' => $assessment->due_time,
                'type' => $assessment->assessmentComponentDetail->assessmentComponent->type,
                'weight' => $assessment->assessmentComponentDetail->weight,
                'status' => $assessment->submission_status,
                'days_until_due' => now()->diffInDays($assessment->due_date, false),
                'urgency' => $this->calculateUrgency($assessment->due_date),
            ];
        })->toArray();
    }

    /**
     * Get registration deadlines
     */
    protected function getRegistrationDeadlines(Semester $semester): array
    {
        return [
            'early_registration_start' => $semester->early_registration_start?->toDateString(),
            'early_registration_end' => $semester->early_registration_end?->toDateString(),
            'regular_registration_start' => $semester->registration_start_date?->toDateString(),
            'regular_registration_end' => $semester->registration_end_date?->toDateString(),
            'late_registration_end' => $semester->late_registration_end?->toDateString(),
        ];
    }

    /**
     * Get important dates
     */
    protected function getImportantDates(Semester $semester): array
    {
        $dates = [];

        if ($semester->orientation_date) {
            $dates[] = [
                'date' => $semester->orientation_date->toDateString(),
                'title' => 'Orientation',
                'type' => 'orientation',
                'description' => 'New student orientation',
            ];
        }

        if ($semester->classes_start_date) {
            $dates[] = [
                'date' => $semester->classes_start_date->toDateString(),
                'title' => 'Classes Begin',
                'type' => 'academic',
                'description' => 'First day of classes',
            ];
        }

        if ($semester->mid_semester_break_start) {
            $dates[] = [
                'date' => $semester->mid_semester_break_start->toDateString(),
                'title' => 'Mid-Semester Break',
                'type' => 'break',
                'description' => 'Mid-semester break begins',
            ];
        }

        if ($semester->classes_end_date) {
            $dates[] = [
                'date' => $semester->classes_end_date->toDateString(),
                'title' => 'Classes End',
                'type' => 'academic',
                'description' => 'Last day of classes',
            ];
        }

        return $dates;
    }

    /**
     * Get upcoming deadlines
     */
    protected function getUpcomingDeadlines(Student $student, Semester $semester): array
    {
        $deadlines = [];
        $now = now();

        // Academic deadlines
        $academicDeadlines = $this->getAcademicDeadlines($semester);
        foreach ($academicDeadlines as $type => $date) {
            if ($date && Carbon::parse($date)->isFuture()) {
                $deadlines[] = [
                    'date' => $date,
                    'title' => $this->getDeadlineTitle($type),
                    'type' => 'academic',
                    'category' => $type,
                    'days_until' => $now->diffInDays(Carbon::parse($date)),
                ];
            }
        }

        // Assessment deadlines
        $assessmentDeadlines = $this->getAssessmentDeadlines($student, $semester);
        foreach ($assessmentDeadlines as $assessment) {
            if ($assessment['days_until_due'] >= 0) {
                $deadlines[] = [
                    'date' => $assessment['due_date'],
                    'title' => $assessment['title'],
                    'type' => 'assessment',
                    'category' => $assessment['type'],
                    'course_code' => $assessment['course_code'],
                    'days_until' => $assessment['days_until_due'],
                    'urgency' => $assessment['urgency'],
                ];
            }
        }

        // Sort by date and take next 10
        usort($deadlines, fn($a, $b) => strcmp($a['date'], $b['date']));

        return array_slice($deadlines, 0, 10);
    }

    /**
     * Group events by month
     */
    protected function groupEventsByMonth(Collection $events): array
    {
        return $events->groupBy(function ($event) {
            return $event->event_date->format('Y-m');
        })->map(function ($monthEvents, $month) {
            return [
                'month' => $month,
                'month_display' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'events' => $monthEvents->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'title' => $event->title,
                        'description' => $event->description,
                        'date' => $event->event_date->toDateString(),
                        'type' => $event->event_type,
                        'category' => $event->category,
                        'is_holiday' => $event->is_holiday,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Get upcoming events
     */
    protected function getUpcomingEvents(Collection $events): array
    {
        return $events->where('event_date', '>=', now())
            ->take(5)
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->event_date->toDateString(),
                    'type' => $event->event_type,
                    'category' => $event->category,
                    'days_until' => now()->diffInDays($event->event_date),
                ];
            })
            ->toArray();
    }

    /**
     * Get event categories
     */
    protected function getEventCategories(Collection $events): array
    {
        return $events->groupBy('category')->map->count()->toArray();
    }

    /**
     * Get holidays
     */
    protected function getHolidays(Collection $events): array
    {
        return $events->where('is_holiday', true)
            ->map(function ($event) {
                return [
                    'title' => $event->title,
                    'date' => $event->event_date->toDateString(),
                    'description' => $event->description,
                ];
            })
            ->toArray();
    }

    /**
     * Get exam periods
     */
    protected function getExamPeriods(Collection $events): array
    {
        return $events->where('event_type', 'exam_period')
            ->map(function ($event) {
                return [
                    'title' => $event->title,
                    'start_date' => $event->event_date->toDateString(),
                    'end_date' => $event->end_date?->toDateString(),
                    'description' => $event->description,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate semester progress
     */
    protected function calculateSemesterProgress(Semester $semester): array
    {
        $now = now();
        $start = $semester->start_date;
        $end = $semester->end_date;

        if ($now < $start) {
            $progress = 0;
            $status = 'not_started';
        } elseif ($now > $end) {
            $progress = 100;
            $status = 'completed';
        } else {
            $totalDays = $start->diffInDays($end);
            $elapsedDays = $start->diffInDays($now);
            $progress = $totalDays > 0 ? round(($elapsedDays / $totalDays) * 100, 1) : 0;
            $status = 'in_progress';
        }

        return [
            'progress_percentage' => $progress,
            'status' => $status,
            'days_elapsed' => max(0, $start->diffInDays($now)),
            'days_remaining' => max(0, $now->diffInDays($end)),
            'weeks_remaining' => max(0, $now->diffInWeeks($end)),
        ];
    }

    /**
     * Get key semester dates
     */
    protected function getKeySemesterDates(Semester $semester): array
    {
        return [
            'semester_start' => $semester->start_date->toDateString(),
            'semester_end' => $semester->end_date->toDateString(),
            'classes_start' => $semester->classes_start_date?->toDateString(),
            'classes_end' => $semester->classes_end_date?->toDateString(),
            'add_drop_deadline' => $semester->add_drop_deadline?->toDateString(),
            'withdrawal_deadline' => $semester->withdrawal_deadline?->toDateString(),
            'final_exams_start' => $semester->final_exam_start_date?->toDateString(),
            'final_exams_end' => $semester->final_exam_end_date?->toDateString(),
        ];
    }

    /**
     * Get enrollment info for current semester
     */
    protected function getEnrollmentInfo(Student $student, Semester $semester): array
    {
        $registrations = $student->courseRegistrations()
            ->where('semester_id', $semester->id)
            ->with('courseOffering.curriculumUnit.unit')
            ->get();

        return [
            'total_courses' => $registrations->count(),
            'registered_courses' => $registrations->where('registration_status', 'registered')->count(),
            'waitlisted_courses' => $registrations->where('registration_status', 'waitlisted')->count(),
            'total_credit_hours' => $registrations->where('registration_status', 'registered')
                ->sum('courseOffering.curriculumUnit.credit_hours'),
            'enrollment_status' => $this->getEnrollmentStatus($registrations),
        ];
    }

    /**
     * Get upcoming milestones
     */
    protected function getUpcomingMilestones(Semester $semester): array
    {
        $milestones = [];
        $now = now();

        $dates = [
            'add_drop_deadline' => 'Add/Drop Deadline',
            'withdrawal_deadline' => 'Withdrawal Deadline',
            'final_exam_start_date' => 'Final Exams Begin',
            'final_exam_end_date' => 'Final Exams End',
            'end_date' => 'Semester Ends',
        ];

        foreach ($dates as $field => $title) {
            $date = $semester->$field;
            if ($date && $date->isFuture()) {
                $milestones[] = [
                    'title' => $title,
                    'date' => $date->toDateString(),
                    'days_until' => $now->diffInDays($date),
                    'type' => 'milestone',
                ];
            }
        }

        return $milestones;
    }

    /**
     * Helper methods
     */
    protected function getSemesterStatus(Semester $semester): string
    {
        $now = now();

        if ($now < $semester->start_date) {
            return 'upcoming';
        } elseif ($now > $semester->end_date) {
            return 'completed';
        } else {
            return 'current';
        }
    }

    protected function isRegistrationOpen(Semester $semester): bool
    {
        $now = now();
        return $semester->registration_start_date &&
            $semester->registration_end_date &&
            $now >= $semester->registration_start_date &&
            $now <= $semester->registration_end_date;
    }

    protected function calculateUrgency(Carbon $dueDate): string
    {
        $daysUntil = now()->diffInDays($dueDate, false);

        return match (true) {
            $daysUntil < 0 => 'overdue',
            $daysUntil <= 1 => 'critical',
            $daysUntil <= 3 => 'high',
            $daysUntil <= 7 => 'medium',
            default => 'low',
        };
    }

    protected function getDeadlineTitle(string $type): string
    {
        return match ($type) {
            'add_drop_deadline' => 'Add/Drop Deadline',
            'withdrawal_deadline' => 'Withdrawal Deadline',
            'final_exam_start' => 'Final Exams Begin',
            'final_exam_end' => 'Final Exams End',
            'grades_due' => 'Grades Due',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    protected function getEnrollmentStatus(Collection $registrations): string
    {
        $registeredCount = $registrations->where('registration_status', 'registered')->count();

        return match (true) {
            $registeredCount === 0 => 'not_enrolled',
            $registeredCount < 3 => 'part_time',
            $registeredCount >= 4 => 'full_time',
            default => 'enrolled',
        };
    }
}
