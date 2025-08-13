<?php

namespace App\Events;

use App\Models\AssessmentComponentDetailScore;
use App\Models\Student;
use App\Models\CourseOffering;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class GradePublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AssessmentComponentDetailScore $gradeScore,
        public Student $student,
        public CourseOffering $courseOffering,
        public array $gradeDetails = []
    ) {
    }

    /**
     * Create event for multiple students
     */
    public static function forMultipleStudents(
        Collection $gradeScores,
        CourseOffering $courseOffering,
        array $additionalData = []
    ): Collection {
        return $gradeScores->map(function ($gradeScore) use ($courseOffering, $additionalData) {
            return new static(
                $gradeScore,
                $gradeScore->courseRegistration->student,
                $courseOffering,
                $additionalData
            );
        });
    }

    /**
     * Get the data for notification processing
     */
    public function getNotificationData(): array
    {
        return [
            'students' => [$this->student],
            'grade_info' => [
                'course_name' => $this->courseOffering->unit->name ?? '',
                'course_code' => $this->courseOffering->unit->code ?? '',
                'assessment_name' => $this->gradeScore->assessmentComponentDetail->name ?? '',
                'grade' => $this->gradeScore->score ?? '',
                'total_points' => $this->gradeScore->assessmentComponentDetail->max_score ?? '',
                'percentage' => $this->gradeScore->percentage ?? '',
                'semester' => $this->courseOffering->semester->name ?? '',
            ],
        ];
    }

    /**
     * Get bulk notification data for multiple students
     */
    public static function getBulkNotificationData(Collection $events): array
    {
        $students = $events->map(fn($event) => $event->student)->unique('id');
        $firstEvent = $events->first();

        return [
            'students' => $students->values()->all(),
            'grade_info' => [
                'course_name' => $firstEvent->courseOffering->unit->name ?? '',
                'course_code' => $firstEvent->courseOffering->unit->code ?? '',
                'assessment_name' => $firstEvent->gradeScore->assessmentComponentDetail->name ?? '',
                'semester' => $firstEvent->courseOffering->semester->name ?? '',
            ],
        ];
    }
}
