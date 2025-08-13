<?php

namespace App\Events;

use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AssessmentDeadlineApproaching
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AssessmentComponentDetail $assessmentDetail,
        public CourseOffering $courseOffering,
        public Collection $recipients, // Can be students, lecturers, or both
        public int $daysUntilDeadline = 0,
        public array $assessmentInfo = []
    ) {
    }

    /**
     * Create event for students
     */
    public static function forStudents(
        AssessmentComponentDetail $assessmentDetail,
        CourseOffering $courseOffering,
        Collection $students,
        int $daysUntilDeadline = 0,
        array $additionalInfo = []
    ): self {
        return new static(
            $assessmentDetail,
            $courseOffering,
            $students,
            $daysUntilDeadline,
            array_merge($additionalInfo, ['recipient_type' => 'students'])
        );
    }

    /**
     * Create event for lecturers
     */
    public static function forLecturers(
        AssessmentComponentDetail $assessmentDetail,
        CourseOffering $courseOffering,
        Collection $lecturers,
        int $daysUntilDeadline = 0,
        array $additionalInfo = []
    ): self {
        return new static(
            $assessmentDetail,
            $courseOffering,
            $lecturers,
            $daysUntilDeadline,
            array_merge($additionalInfo, ['recipient_type' => 'lecturers'])
        );
    }

    /**
     * Get the data for notification processing
     */
    public function getNotificationData(): array
    {
        $deadlineText = $this->daysUntilDeadline === 0
            ? 'today'
            : "in {$this->daysUntilDeadline} day(s)";

        return [
            'recipients' => $this->recipients->all(),
            'assessment_info' => [
                'name' => $this->assessmentDetail->name ?? 'Assessment',
                'course' => $this->courseOffering->unit->name ?? '',
                'course_code' => $this->courseOffering->unit->code ?? '',
                'deadline' => $this->assessmentDetail->due_date?->format('Y-m-d H:i') ?? '',
                'deadline_text' => $deadlineText,
                'max_score' => $this->assessmentDetail->max_score ?? '',
                'weight' => $this->assessmentDetail->weight ?? '',
                'link' => $this->assessmentInfo['submission_link'] ?? '',
                'instructions' => $this->assessmentInfo['instructions'] ?? '',
                'recipient_type' => $this->assessmentInfo['recipient_type'] ?? 'students',
            ],
        ];
    }

    /**
     * Check if this is a critical deadline (1 day or less)
     */
    public function isCriticalDeadline(): bool
    {
        return $this->daysUntilDeadline <= 1;
    }

    /**
     * Get the appropriate notification template type based on recipient
     */
    public function getNotificationTemplateType(): string
    {
        $recipientType = $this->assessmentInfo['recipient_type'] ?? 'students';

        return $recipientType === 'lecturers'
            ? 'lecturer_assessment_deadline'
            : 'student_assessment_deadline';
    }
}
