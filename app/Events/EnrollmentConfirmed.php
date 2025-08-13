<?php

namespace App\Events;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\CourseOffering;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class EnrollmentConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Enrollment $enrollment,
        public Student $student,
        public CourseOffering $courseOffering,
        public array $enrollmentDetails = []
    ) {
    }

    /**
     * Create event for multiple students
     */
    public static function forMultipleStudents(
        Collection $enrollments,
        array $additionalData = []
    ): Collection {
        return $enrollments->map(function ($enrollment) use ($additionalData) {
            return new static(
                $enrollment,
                $enrollment->student,
                $enrollment->courseOffering,
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
            'enrollment_info' => [
                'course_name' => $this->courseOffering->unit->name ?? '',
                'course_code' => $this->courseOffering->unit->code ?? '',
                'semester' => $this->courseOffering->semester->name ?? '',
                'start_date' => $this->courseOffering->start_date?->format('Y-m-d') ?? '',
                'end_date' => $this->courseOffering->end_date?->format('Y-m-d') ?? '',
                'credits' => $this->courseOffering->unit->credits ?? '',
                'lecturer' => $this->enrollmentDetails['lecturer_name'] ?? '',
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
            'enrollment_info' => [
                'course_name' => $firstEvent->courseOffering->unit->name ?? '',
                'course_code' => $firstEvent->courseOffering->unit->code ?? '',
                'semester' => $firstEvent->courseOffering->semester->name ?? '',
                'start_date' => $firstEvent->courseOffering->start_date?->format('Y-m-d') ?? '',
                'end_date' => $firstEvent->courseOffering->end_date?->format('Y-m-d') ?? '',
                'credits' => $firstEvent->courseOffering->unit->credits ?? '',
            ],
        ];
    }
}
