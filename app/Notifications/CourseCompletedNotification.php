<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CourseCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $courseCode,
        public string $courseName,
        public string $grade,
        public float $finalPercentage,
        public float $creditPoints,
        public bool $passed,
        public ?string $message = null
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get database representation (matches custom notifications table schema)
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->passed
            ? "Course Completed: {$this->courseCode}"
            : "Course Completed (Not Passed): {$this->courseCode}";

        $detailedMessage = $this->passed
            ? "Congratulations! You have successfully completed {$this->courseName} with grade {$this->grade} ({$this->finalPercentage}%). You earned {$this->creditPoints} credit points."
            : "You have completed {$this->courseName} with grade {$this->grade} ({$this->finalPercentage}%). Unfortunately, you did not meet the passing threshold. Please consult with your academic advisor.";

        if ($this->message) {
            $detailedMessage .= "\n\n{$this->message}";
        }

        return [
            'type' => 'course_completed',
            'category' => NotificationCategory::ACADEMIC->value,
            'title' => $title,
            'message' => $detailedMessage,
            'data' => [
                'course_code' => $this->courseCode,
                'course_name' => $this->courseName,
                'grade' => $this->grade,
                'final_percentage' => $this->finalPercentage,
                'credit_points' => $this->creditPoints,
                'passed' => $this->passed,
                'icon' => $this->passed ? '🎓' : '📚',
                'action_url' => '/student/academic-records',
                'action_text' => 'View Academic Records',
            ],
            'is_important' => !$this->passed, // Only failed courses are marked important
            'channels' => ['database', 'broadcast'],
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
