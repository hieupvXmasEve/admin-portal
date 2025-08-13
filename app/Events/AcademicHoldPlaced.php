<?php

namespace App\Events;

use App\Models\AcademicHold;
use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AcademicHoldPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AcademicHold $academicHold,
        public Student $student,
        public array $holdDetails = []
    ) {
    }

    /**
     * Create event for multiple students
     */
    public static function forMultipleStudents(
        Collection $academicHolds,
        array $additionalData = []
    ): Collection {
        return $academicHolds->map(function ($hold) use ($additionalData) {
            return new static(
                $hold,
                $hold->student,
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
            'hold_info' => [
                'type' => $this->academicHold->type ?? 'Academic Hold',
                'reason' => $this->academicHold->reason ?? 'Please contact the academic office',
                'contact' => $this->holdDetails['contact_info'] ?? 'Academic Affairs Office',
                'placed_date' => $this->academicHold->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'semester' => $this->academicHold->semester->name ?? '',
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
            'hold_info' => [
                'type' => $firstEvent->academicHold->type ?? 'Academic Hold',
                'reason' => $firstEvent->academicHold->reason ?? 'Please contact the academic office',
                'contact' => $firstEvent->holdDetails['contact_info'] ?? 'Academic Affairs Office',
                'placed_date' => $firstEvent->academicHold->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            ],
        ];
    }
}
