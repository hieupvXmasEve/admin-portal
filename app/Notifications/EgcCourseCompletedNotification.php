<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EgcCourseCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $courseCode,
        public string $courseName,
        public string $grade,
        public bool $passed,
        public bool $levelProgressed,
        public int $currentLevel,
        public string $message
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $title = $this->passed
            ? "EGC Course Completed: {$this->courseCode}"
            : "EGC Course Result: {$this->courseCode}";

        $detailedMessage = $this->passed
            ? "You passed {$this->courseCode} - {$this->courseName} with grade {$this->grade}. {$this->message}"
            : "You did not pass {$this->courseCode} - {$this->courseName}. Grade: {$this->grade}. {$this->message}";

        return [
            'type' => 'egc_course_completed',
            'category' => NotificationCategory::ACADEMIC->value,
            'title' => $title,
            'message' => $detailedMessage,
            'data' => [
                'course_code' => $this->courseCode,
                'course_name' => $this->courseName,
                'grade' => $this->grade,
                'passed' => $this->passed,
                'level_progressed' => $this->levelProgressed,
                'current_level' => $this->currentLevel,
                'icon' => $this->passed ? '✅' : '❌',
                'action_url' => '/student/academic-records',
                'action_text' => 'View Academic Records',
            ],
            'is_important' => true,
            'channels' => ['database', 'broadcast'],
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
