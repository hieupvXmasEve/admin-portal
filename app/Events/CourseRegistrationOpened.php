<?php

namespace App\Events;

use App\Models\CourseOffering;
use App\Models\Semester;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CourseRegistrationOpened
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CourseOffering $courseOffering,
        public Semester $semester,
        public array $lecturers = [],
        public ?\DateTime $registrationDeadline = null
    ) {
    }

    /**
     * Get the data for notification processing
     */
    public function getNotificationData(): array
    {
        return [
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'lecturers' => $this->lecturers,
            'course_info' => [
                'name' => $this->courseOffering->unit->name ?? 'Course',
                'code' => $this->courseOffering->unit->code ?? '',
                'semester' => $this->semester->name ?? '',
                'deadline' => $this->registrationDeadline?->format('Y-m-d H:i:s') ?? '',
            ],
        ];
    }
}
