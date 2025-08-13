<?php

namespace App\Services;

use App\Events\AssessmentDeadlineApproaching;
use App\Events\CourseRegistrationOpened;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduledNotificationService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {
    }

    /**
     * Schedule all assessment deadline reminders for a semester
     */
    public function scheduleAssessmentRemindersForSemester(
        Semester $semester,
        array $reminderDays = [7, 3, 1]
    ): array {
        $results = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all assessments for the semester
        $assessments = AssessmentComponentDetail::whereNotNull('due_date')
            ->whereHas('assessmentComponent.syllabus.courseOffering', function ($query) use ($semester) {
                $query->where('semester_id', $semester->id);
            })
            ->where('due_date', '>', now())
            ->with(['assessmentComponent.syllabus.courseOffering.unit'])
            ->get();

        foreach ($assessments as $assessment) {
            try {
                $courseOffering = $assessment->assessmentComponent->syllabus->courseOffering;

                foreach ($reminderDays as $days) {
                    $reminderDate = $assessment->due_date->subDays($days);

                    // Only schedule if reminder date is in the future
                    if ($reminderDate->isFuture()) {
                        $this->scheduleAssessmentReminder($assessment, $courseOffering, $days, $reminderDate);
                        $results['scheduled']++;
                    } else {
                        $results['skipped']++;
                    }
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to schedule assessment reminder', [
                    'assessment_id' => $assessment->id,
                    'semester_id' => $semester->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Semester assessment reminders scheduled', [
            'semester_id' => $semester->id,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Schedule course registration reminders
     */
    public function scheduleCourseRegistrationReminders(
        Semester $semester,
        array $reminderDays = [14, 7, 3, 1]
    ): array {
        $results = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get course offerings for the semester
        $courseOfferings = CourseOffering::where('semester_id', $semester->id)
            ->whereNotNull('registration_start_date')
            ->where('registration_start_date', '>', now())
            ->with(['unit', 'teachingAssignments.user'])
            ->get();

        foreach ($courseOfferings as $courseOffering) {
            try {
                foreach ($reminderDays as $days) {
                    $reminderDate = $courseOffering->registration_start_date->subDays($days);

                    if ($reminderDate->isFuture()) {
                        $this->scheduleCourseRegistrationReminder($courseOffering, $semester, $days, $reminderDate);
                        $results['scheduled']++;
                    } else {
                        $results['skipped']++;
                    }
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'course_offering_id' => $courseOffering->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to schedule course registration reminder', [
                    'course_offering_id' => $courseOffering->id,
                    'semester_id' => $semester->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Schedule grade submission reminders for lecturers
     */
    public function scheduleGradeSubmissionReminders(
        Semester $semester,
        Carbon $gradeSubmissionDeadline,
        array $reminderDays = [7, 3, 1]
    ): array {
        $results = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all lecturers teaching in this semester
        $lecturers = User::whereHas('teachingAssignments.courseOffering', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id);
        })->with(['teachingAssignments.courseOffering.unit'])->get();

        foreach ($reminderDays as $days) {
            $reminderDate = $gradeSubmissionDeadline->copy()->subDays($days);

            if ($reminderDate->isFuture()) {
                try {
                    $this->notificationService->scheduleReminder(
                        'grade_submission_reminder',
                        $lecturers,
                        $reminderDate,
                        [
                            'semester_id' => $semester->id,
                            'deadline' => $gradeSubmissionDeadline->format('Y-m-d H:i'),
                            'days_remaining' => $days,
                            'semester_name' => $semester->name,
                        ]
                    );

                    $results['scheduled']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'reminder_days' => $days,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    /**
     * Schedule system maintenance notifications
     */
    public function scheduleMaintenanceNotifications(
        Carbon $maintenanceDate,
        string $maintenanceDescription,
        array $reminderDays = [7, 3, 1]
    ): array {
        $results = [
            'scheduled' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all active users
        $allUsers = User::where('is_active', true)->get();

        foreach ($reminderDays as $days) {
            $reminderDate = $maintenanceDate->copy()->subDays($days);

            if ($reminderDate->isFuture()) {
                try {
                    $this->notificationService->scheduleReminder(
                        'system_maintenance',
                        $allUsers,
                        $reminderDate,
                        [
                            'announcement' => [
                                'title' => 'Scheduled System Maintenance',
                                'body' => $maintenanceDescription,
                                'date' => $maintenanceDate->format('Y-m-d H:i'),
                            ],
                            'days_until_maintenance' => $days,
                        ]
                    );

                    $results['scheduled']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'reminder_days' => $days,
                        'error' => $e->getMessage(),
                    ];
                }
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    /**
     * Schedule individual assessment reminder
     */
    protected function scheduleAssessmentReminder(
        AssessmentComponentDetail $assessment,
        CourseOffering $courseOffering,
        int $days,
        Carbon $reminderDate
    ): void {
        // Get students enrolled in the course
        $students = Student::whereHas('enrollments', function ($query) use ($courseOffering) {
            $query->where('course_offering_id', $courseOffering->id);
        })->with('user')->get();

        // Get lecturers assigned to the course
        $lecturers = User::whereHas('teachingAssignments', function ($query) use ($courseOffering) {
            $query->where('course_offering_id', $courseOffering->id);
        })->get();

        // Schedule for students
        if ($students->isNotEmpty()) {
            $studentUsers = $students->map(fn($student) => $student->user)->filter();

            $this->notificationService->scheduleReminder(
                'assessment_deadline_approaching',
                $studentUsers,
                $reminderDate,
                [
                    'assessment_info' => [
                        'name' => $assessment->name,
                        'course' => $courseOffering->unit->name,
                        'course_code' => $courseOffering->unit->code,
                        'deadline' => $assessment->due_date->format('Y-m-d H:i'),
                        'days_remaining' => $days,
                        'link' => config('app.url') . "/courses/{$courseOffering->id}/assessments/{$assessment->id}",
                    ],
                    'recipient_type' => 'students',
                ]
            );
        }

        // Schedule for lecturers (different content)
        if ($lecturers->isNotEmpty()) {
            $this->notificationService->scheduleReminder(
                'assessment_deadline_approaching',
                $lecturers,
                $reminderDate,
                [
                    'assessment_info' => [
                        'name' => $assessment->name,
                        'course' => $courseOffering->unit->name,
                        'course_code' => $courseOffering->unit->code,
                        'deadline' => $assessment->due_date->format('Y-m-d H:i'),
                        'days_remaining' => $days,
                        'link' => config('app.url') . "/lecturer/courses/{$courseOffering->id}/assessments/{$assessment->id}",
                    ],
                    'recipient_type' => 'lecturers',
                ]
            );
        }
    }

    /**
     * Schedule course registration reminder
     */
    protected function scheduleCourseRegistrationReminder(
        CourseOffering $courseOffering,
        Semester $semester,
        int $days,
        Carbon $reminderDate
    ): void {
        // Get lecturers assigned to this course
        $lecturers = $courseOffering->teachingAssignments->map(fn($assignment) => $assignment->user);

        if ($lecturers->isNotEmpty()) {
            $this->notificationService->scheduleReminder(
                'course_registration_opened',
                $lecturers,
                $reminderDate,
                [
                    'course_info' => [
                        'name' => $courseOffering->unit->name,
                        'code' => $courseOffering->unit->code,
                        'semester' => $semester->name,
                        'registration_start' => $courseOffering->registration_start_date->format('Y-m-d H:i'),
                        'days_until_registration' => $days,
                    ],
                ]
            );
        }
    }
}
