<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Jobs\SendSingleEmailJob;
use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        protected EmailService $emailService,
        protected EmailTemplateService $templateService
    ) {
    }

    /**
     * Send academic notification to users
     */
    public function sendAcademicNotification(
        string $eventType,
        Collection|array $recipients,
        array $data = [],
        bool $checkPreferences = true
    ): array {
        // Get appropriate template for the event
        $template = EmailTemplate::getLatestVersion($eventType);

        if (!$template) {
            Log::warning('No email template found for event type', ['event_type' => $eventType]);
            return ['success' => false, 'message' => 'No template found for notification type'];
        }

        // Ensure recipients is a collection
        if (is_array($recipients)) {
            $recipients = collect($recipients);
        }

        $sent = [];
        $skipped = [];

        foreach ($recipients as $recipient) {
            try {
                // Get user email
                $email = $this->getUserEmail($recipient);
                if (!$email) {
                    $skipped[] = ['recipient' => $recipient, 'reason' => 'No email address'];
                    continue;
                }

                // Check user preferences if needed
                if ($checkPreferences && $recipient instanceof User) {
                    if (!$this->emailService->canUserReceiveNotification($recipient, $eventType)) {
                        $skipped[] = ['recipient' => $email, 'reason' => 'User preference'];
                        continue;
                    }
                }

                // Prepare template variables
                $variables = $this->prepareTemplateVariables($eventType, $recipient, $data);

                // Render template
                $rendered = $this->templateService->renderTemplate($template, $variables);

                // Send email
                $emailLog = $this->emailService->sendSingleEmail(
                    $email,
                    $rendered['subject'],
                    $rendered['html'],
                    $template,
                    [],
                    null
                );

                $sent[] = ['recipient' => $email, 'email_log_id' => $emailLog->id];

                // Update user preference last sent time
                if ($recipient instanceof User) {
                    $preference = UserEmailPreference::getUserPreference($recipient->id, $eventType);
                    $preference?->markAsSent();
                }

            } catch (\Exception $e) {
                Log::error('Failed to send academic notification', [
                    'event_type' => $eventType,
                    'recipient' => $email ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $skipped[] = ['recipient' => $email ?? 'unknown', 'reason' => $e->getMessage()];
            }
        }

        return [
            'success' => true,
            'sent' => $sent,
            'skipped' => $skipped,
            'total_sent' => count($sent),
            'total_skipped' => count($skipped),
        ];
    }

    /**
     * Schedule a reminder notification
     */
    public function scheduleReminder(
        string $type,
        Collection|array $recipients,
        \DateTimeInterface $scheduleTime,
        array $data = []
    ): void {
        // This will dispatch a job at the scheduled time
        dispatch(new ProcessNotificationJob(
            $type,
            $recipients instanceof Collection ? $recipients->toArray() : $recipients,
            $data
        ))->delay($scheduleTime);

        Log::info('Reminder scheduled', [
            'type' => $type,
            'recipients_count' => count($recipients),
            'scheduled_for' => $scheduleTime->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Process event-based notification
     */
    public function processEventNotification(string $event, array $data = []): array
    {
        $results = [];

        switch ($event) {
            case 'course_registration_opened':
                $results = $this->sendCourseRegistrationNotification($data);
                break;

            case 'grades_published':
                $results = $this->sendGradeNotification($data);
                break;

            case 'academic_hold_placed':
                $results = $this->sendAcademicHoldNotification($data);
                break;

            case 'enrollment_confirmed':
                $results = $this->sendEnrollmentConfirmation($data);
                break;

            case 'assessment_deadline_approaching':
                $results = $this->sendAssessmentDeadlineReminder($data);
                break;

            case 'assessment_final_reminder':
                $results = $this->sendAssessmentFinalReminder($data);
                break;

            case 'system_maintenance':
                $results = $this->sendSystemAnnouncementNotification($data);
                break;

            default:
                Log::warning('Unknown notification event', ['event' => $event]);
                $results = ['success' => false, 'message' => 'Unknown event type'];
        }

        return $results;
    }

    /**
     * Send course registration notification
     */
    protected function sendCourseRegistrationNotification(array $data): array
    {
        $lecturers = $data['lecturers'] ?? [];
        $courseInfo = $data['course_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_COURSE_REGISTRATION,
            $lecturers,
            [
                'course_name' => $courseInfo['name'] ?? 'Course',
                'course_code' => $courseInfo['code'] ?? '',
                'semester' => $courseInfo['semester'] ?? '',
                'registration_deadline' => $courseInfo['deadline'] ?? '',
            ]
        );
    }

    /**
     * Send grade notification
     */
    protected function sendGradeNotification(array $data): array
    {
        $students = $data['students'] ?? [];
        $gradeInfo = $data['grade_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_GRADE_NOTIFICATION,
            $students,
            [
                'course_name' => $gradeInfo['course_name'] ?? '',
                'assessment_name' => $gradeInfo['assessment_name'] ?? '',
                'grade' => $gradeInfo['grade'] ?? '',
                'total_points' => $gradeInfo['total_points'] ?? '',
            ]
        );
    }

    /**
     * Send academic hold notification
     */
    protected function sendAcademicHoldNotification(array $data): array
    {
        $students = $data['students'] ?? [];
        $holdInfo = $data['hold_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_ACADEMIC_HOLD,
            $students,
            [
                'hold_type' => $holdInfo['type'] ?? '',
                'hold_reason' => $holdInfo['reason'] ?? '',
                'contact_info' => $holdInfo['contact'] ?? '',
            ]
        );
    }

    /**
     * Send enrollment confirmation
     */
    protected function sendEnrollmentConfirmation(array $data): array
    {
        $students = $data['students'] ?? [];
        $enrollmentInfo = $data['enrollment_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_ENROLLMENT_CONFIRMATION,
            $students,
            [
                'course_name' => $enrollmentInfo['course_name'] ?? '',
                'course_code' => $enrollmentInfo['course_code'] ?? '',
                'semester' => $enrollmentInfo['semester'] ?? '',
                'start_date' => $enrollmentInfo['start_date'] ?? '',
            ]
        );
    }

    /**
     * Send assessment deadline reminder
     */
    protected function sendAssessmentDeadlineReminder(array $data): array
    {
        $recipients = $data['recipients'] ?? [];
        $assessmentInfo = $data['assessment_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
            $recipients,
            [
                'assessment_name' => $assessmentInfo['name'] ?? '',
                'course_name' => $assessmentInfo['course'] ?? '',
                'deadline' => $assessmentInfo['deadline'] ?? '',
                'submission_link' => $assessmentInfo['link'] ?? '',
            ]
        );
    }

    /**
     * Send system announcement notification
     */
    protected function sendSystemAnnouncementNotification(array $data): array
    {
        $recipients = $data['recipients'] ?? [];
        $announcement = $data['announcement'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_SYSTEM_ANNOUNCEMENT,
            $recipients,
            [
                'announcement_title' => $announcement['title'] ?? '',
                'announcement_body' => $announcement['body'] ?? '',
                'effective_date' => $announcement['date'] ?? '',
            ],
            false // Don't check preferences for system announcements
        );
    }

    /**
     * Send assessment final reminder (critical deadline)
     */
    protected function sendAssessmentFinalReminder(array $data): array
    {
        $recipients = $data['recipients'] ?? [];
        $assessmentInfo = $data['assessment_info'] ?? [];

        return $this->sendAcademicNotification(
            UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
            $recipients,
            [
                'assessment_name' => $assessmentInfo['name'] ?? '',
                'course_name' => $assessmentInfo['course'] ?? '',
                'deadline' => $assessmentInfo['deadline'] ?? '',
                'deadline_text' => 'in ' . ($assessmentInfo['hours_until_deadline'] ?? 2) . ' hours',
                'submission_link' => $assessmentInfo['link'] ?? '',
                'is_final_reminder' => true,
                'urgency_level' => 'critical',
            ]
        );
    }

    /**
     * Route notifications based on user roles and event type
     */
    public function routeNotificationByRole(
        string $eventType,
        array $data = [],
        array $targetRoles = []
    ): array {
        $results = [];

        foreach ($targetRoles as $role) {
            try {
                $recipients = $this->getRecipientsByRole($role, $data);

                if ($recipients->isNotEmpty()) {
                    $roleResult = $this->sendAcademicNotification(
                        $eventType,
                        $recipients,
                        $data
                    );

                    $results[$role] = $roleResult;

                    Log::info('Role-based notification sent', [
                        'event_type' => $eventType,
                        'role' => $role,
                        'recipients_count' => $recipients->count(),
                        'sent_count' => $roleResult['total_sent'] ?? 0,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send role-based notification', [
                    'event_type' => $eventType,
                    'role' => $role,
                    'error' => $e->getMessage(),
                ]);

                $results[$role] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get recipients by role with optional filtering
     */
    protected function getRecipientsByRole(string $role, array $data = []): Collection
    {
        $query = User::whereHas('campusUserRoles', function ($query) use ($role) {
            $query->whereHas('role', function ($roleQuery) use ($role) {
                $roleQuery->where('name', $role);
            });
        });

        // Apply additional filters based on data context
        if (isset($data['campus_id'])) {
            $query->whereHas('campusUserRoles', function ($campusQuery) use ($data) {
                $campusQuery->where('campus_id', $data['campus_id']);
            });
        }

        if (isset($data['program_id']) && $role === 'student') {
            $query->whereHas('student', function ($studentQuery) use ($data) {
                $studentQuery->where('program_id', $data['program_id']);
            });
        }

        if (isset($data['course_offering_id']) && in_array($role, ['lecturer', 'student'])) {
            if ($role === 'lecturer') {
                // Get lecturers assigned to this course offering
                $query->whereHas('teachingAssignments', function ($teachingQuery) use ($data) {
                    $teachingQuery->where('course_offering_id', $data['course_offering_id']);
                });
            } elseif ($role === 'student') {
                // Get students enrolled in this course offering
                $query->whereHas('student.enrollments', function ($enrollmentQuery) use ($data) {
                    $enrollmentQuery->where('course_offering_id', $data['course_offering_id']);
                });
            }
        }

        return $query->get();
    }

    /**
     * Schedule recurring reminders
     */
    public function scheduleRecurringReminder(
        string $type,
        Collection|array $recipients,
        string $frequency, // 'daily', 'weekly', 'monthly'
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null,
        array $data = []
    ): array {
        $scheduledJobs = [];
        $currentDate = clone $startDate;
        $endDate = $endDate ?? $startDate->modify('+1 year');

        while ($currentDate <= $endDate) {
            $scheduledJobs[] = [
                'scheduled_for' => $currentDate->format('Y-m-d H:i:s'),
                'job_id' => dispatch(new ProcessNotificationJob(
                    $type,
                    $recipients instanceof Collection ? $recipients->toArray() : $recipients,
                    array_merge($data, [
                        'is_recurring' => true,
                        'frequency' => $frequency,
                        'occurrence_date' => $currentDate->format('Y-m-d'),
                    ])
                ))->delay($currentDate),
            ];

            // Calculate next occurrence
            $currentDate = match ($frequency) {
                'daily' => $currentDate->modify('+1 day'),
                'weekly' => $currentDate->modify('+1 week'),
                'monthly' => $currentDate->modify('+1 month'),
                default => $currentDate->modify('+1 day'),
            };
        }

        Log::info('Recurring reminders scheduled', [
            'type' => $type,
            'frequency' => $frequency,
            'recipients_count' => count($recipients),
            'occurrences' => count($scheduledJobs),
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);

        return $scheduledJobs;
    }

    /**
     * Send notification with priority handling
     */
    public function sendPriorityNotification(
        string $eventType,
        Collection|array $recipients,
        array $data = [],
        string $priority = 'normal' // 'low', 'normal', 'high', 'critical'
    ): array {
        // Add priority information to data
        $data['priority'] = $priority;
        $data['priority_timestamp'] = now()->toISOString();

        // For critical notifications, bypass user preferences
        $checkPreferences = $priority !== 'critical';

        $result = $this->sendAcademicNotification(
            $eventType,
            $recipients,
            $data,
            $checkPreferences
        );

        // Log priority notifications
        Log::info('Priority notification sent', [
            'event_type' => $eventType,
            'priority' => $priority,
            'recipients_count' => count($recipients),
            'sent_count' => $result['total_sent'] ?? 0,
            'bypassed_preferences' => !$checkPreferences,
        ]);

        return $result;
    }

    /**
     * Get user email from various recipient types
     */
    protected function getUserEmail($recipient): ?string
    {
        if (is_string($recipient)) {
            return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
        }

        if ($recipient instanceof User) {
            return $recipient->email;
        }

        if (is_array($recipient) && isset($recipient['email'])) {
            return $recipient['email'];
        }

        return null;
    }

    /**
     * Prepare template variables based on event type and recipient
     */
    protected function prepareTemplateVariables(string $eventType, $recipient, array $data): array
    {
        $variables = $data;

        // Add recipient-specific variables
        if ($recipient instanceof User) {
            $variables['user_name'] = $recipient->name;
            $variables['user_email'] = $recipient->email;
            $variables['user_id'] = $recipient->id;

            // Add role-specific variables
            if (method_exists($recipient, 'hasRole')) {
                if ($recipient->hasRole('student')) {
                    $variables['student_name'] = $recipient->name;
                    $variables['student_id'] = $recipient->student_id ?? $recipient->id;
                } elseif ($recipient->hasRole('lecturer')) {
                    $variables['lecturer_name'] = $recipient->name;
                    $variables['lecturer_id'] = $recipient->lecturer_id ?? $recipient->id;
                }
            }
        }

        // Add common variables
        $variables['current_date'] = now()->format('Y-m-d');
        $variables['current_time'] = now()->format('H:i');
        $variables['system_name'] = config('app.name');
        $variables['system_url'] = config('app.url');
        $variables['login_url'] = config('app.url') . '/login';

        return $variables;
    }
}
