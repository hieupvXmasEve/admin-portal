<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EgcLevelMismatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public CourseOffering $courseOffering,
        public array $warningData
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $message = "Student {$this->student->student_id} ({$this->student->full_name}) passed {$this->warningData['unit_code']} ".
                   "with grade {$this->warningData['final_grade']}, but there is a level mismatch. ".
                   "Student's level: {$this->warningData['student_level']}, Unit level: {$this->warningData['unit_level']}. ".
                   "Grade has been recorded but student level was NOT progressed. Please review this case.";

        return [
            'type' => 'egc_level_mismatch',
            'category' => NotificationCategory::ADMIN->value,
            'title' => '⚠️ EGC Level Mismatch Detected',
            'message' => $message,
            'data' => [
                'student_id' => $this->student->student_id,
                'student_db_id' => $this->student->id,
                'student_name' => $this->student->full_name,
                'student_level' => $this->warningData['student_level'],
                'unit_level' => $this->warningData['unit_level'],
                'unit_code' => $this->warningData['unit_code'],
                'final_grade' => $this->warningData['final_grade'],
                'course_offering_id' => $this->courseOffering->id,
                'icon' => '⚠️',
                'priority' => 'high',
                'action_url' => "/students/{$this->student->id}",
                'action_text' => 'View Student Record',
                'action_items' => [
                    'Verify the student was enrolled in the correct level',
                    'Check if manual intervention is needed',
                    'Contact the student if necessary',
                ],
            ],
            'is_important' => true,
            'channels' => ['database'],
        ];
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
