<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Facilities\Models\Room;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use App\Shared\Contracts\Identity\ActiveLecturerReader;

final class GetClassSessionFormOptionsQuery
{
    public function __construct(private readonly ActiveLecturerReader $lecturersReader) {}

    public function handle(string $operation, mixed ...$arguments): mixed
    {
        return $this->{$operation}(...$arguments);
    }

    /** @return array<string, mixed> */
    public function create(): array
    {
        return ['courseOfferings' => app(ClassSessionService::class)->getCourseOfferingsForSelect()];
    }

    /** @return array<string, mixed> */
    public function edit(ClassSession $classSession): array
    {
        return [
            'session' => $classSession,
            'courseOfferings' => app(ClassSessionService::class)->getCourseOfferingsForSelect(),
            'rooms' => $this->roomsForCampus((int) app('campus')->id),
        ];
    }

    /** @return array<string, mixed> */
    public function createForOffering(CourseOffering $courseOffering): array
    {
        return [
            'courseOffering' => [
                'id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'campus_id' => $courseOffering->campus_id,
                'schedule_time_start' => $courseOffering->schedule_time_start?->format('H:i'),
                'schedule_time_end' => $courseOffering->schedule_time_end?->format('H:i'),
                'syllabus_template' => $courseOffering->syllabusTemplate ? ['total_sessions' => $courseOffering->syllabusTemplate->total_sessions] : null,
                'class_sessions_count' => $courseOffering->classSessions()->count(),
            ],
            'rooms' => $this->bookableRooms((int) $courseOffering->campus_id),
            'lecturers' => $this->lecturers(),
        ];
    }

    /** @return array<string, mixed> */
    public function editModal(ClassSession $classSession): array
    {
        $courseOffering = $classSession->courseOffering;
        $classSession->load('room:id,name', 'lecture:id,first_name,last_name');

        return [
            'session' => $classSession,
            'rooms' => $this->bookableRooms((int) $courseOffering->campus_id),
            'lecturers' => $this->lecturers(),
        ];
    }

    /** @param array<int, string> $sessionIds @return array<string, mixed> */
    public function bulkEdit(CourseOffering $courseOffering, array $sessionIds): array
    {
        return [
            'courseOffering' => ['id' => $courseOffering->id],
            'sessions' => ClassSession::query()
                ->whereIn('id', $sessionIds)
                ->where('course_offering_id', $courseOffering->id)
                ->get(['id', 'session_title', 'session_date', 'start_time', 'end_time', 'course_offering_id']),
            'rooms' => $this->bookableRooms((int) $courseOffering->campus_id),
            'lecturers' => $this->lecturers(),
        ];
    }

    private function roomsForCampus(int $campusId): mixed
    {
        return Room::forCampus($campusId)
            ->with('building:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);
    }

    private function bookableRooms(int $campusId): mixed
    {
        return Room::bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->forCampus($campusId)
            ->with('building:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);
    }

    private function lecturers(): mixed
    {
        return $this->lecturersReader->all();
    }
}
