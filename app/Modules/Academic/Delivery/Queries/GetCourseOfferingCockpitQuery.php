<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use Illuminate\Support\Facades\DB;

final class GetCourseOfferingCockpitQuery
{
    public function __construct(
        private readonly SpaceReferenceReader $spaceReferences,
    ) {}

    /**
     * @return array{courseOffering: object, availableRooms: list<array<string, mixed>>, siblingOfferings: mixed}
     */
    public function handle(object $courseOffering, int $campusId): array
    {
        $courseOffering->load([
            'semester',
            'unit',
            'syllabusTemplate.unit:id,code,name',
            'syllabusTemplate.applicableCampus:id,name',
            'syllabusTemplate.applicableProgram:id,name',
            'lecture',
            'courseRegistrations' => function ($query): void {
                $query->with('student')->orderBy('registration_date', 'desc');
            },
            'academicRecords' => function ($query): void {
                $query
                    ->select(['id', 'student_id', 'course_offering_id', 'is_repeat_course', 'attempt_number', 'original_record_id'])
                    ->with('originalRecord:id,final_letter_grade,final_percentage,completion_status');
            },
            'classSessions' => function ($query): void {
                $query
                    ->with('room:id,name', 'lecture:id,first_name,last_name')
                    ->select([
                        'id', 'course_offering_id', 'room_id', 'lecture_id', 'session_title',
                        'session_description', 'session_date', 'start_time', 'end_time',
                        'session_type', 'status', 'attendance_percentage',
                    ])
                    ->orderBy('session_date')
                    ->orderBy('start_time');
            },
            'formTargets' => function ($query): void {
                $query->select(['id', 'scope_type', 'scope_id']);
            },
        ]);

        $siblingOfferings = $courseOffering->newQuery()
            ->where('unit_id', $courseOffering->unit_id)
            ->where('semester_id', $courseOffering->semester_id)
            ->where('id', '!=', $courseOffering->id)
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->with(['lecture:id,first_name,last_name'])
            ->get([
                'id', 'section_code', 'current_enrollment', 'max_capacity', 'schedule_days',
                'schedule_time_start', 'schedule_time_end', 'lecture_id',
            ]);

        return [
            'courseOffering' => $courseOffering,
            'availableRooms' => $this->availableRooms($courseOffering, $campusId),
            'siblingOfferings' => $siblingOfferings,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function availableRooms(object $courseOffering, int $campusId): array
    {
        $rooms = $this->spaceReferences->forCampus($campusId);
        $rooms = array_values(array_filter(
            $rooms,
            static fn (object $room): bool => ($room->status === null || $room->status === 'available') && $room->isBookable,
        ));
        $scheduleDays = is_array($courseOffering->schedule_days ?? null) ? $courseOffering->schedule_days : [];
        $startTime = $courseOffering->schedule_time_start?->format('H:i');
        $endTime = $courseOffering->schedule_time_end?->format('H:i');

        if ($scheduleDays === [] || $startTime === null || $endTime === null) {
            return array_map(static fn (object $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'code' => $room->code,
                'capacity' => $room->capacity,
                'type' => $room->type,
                'building' => $room->building,
            ], $rooms);
        }

        $dayIndexes = [
            'monday' => 0,
            'tuesday' => 1,
            'wednesday' => 2,
            'thursday' => 3,
            'friday' => 4,
            'saturday' => 5,
            'sunday' => 6,
        ];
        $weekdayIndexes = collect($scheduleDays)
            ->map(fn (mixed $day): ?int => $dayIndexes[strtolower((string) $day)] ?? null)
            ->filter(static fn (?int $day): bool => $day !== null)
            ->values()
            ->all();

        if ($weekdayIndexes === []) {
            return $this->mapRooms($rooms);
        }

        $roomIds = array_map(static fn (object $room): int => $room->id, $rooms);
        $conflictingRoomIds = DB::table('class_sessions')
            ->join('course_offerings', 'course_offerings.id', '=', 'class_sessions.course_offering_id')
            ->whereIn('class_sessions.room_id', $roomIds)
            ->whereIn(DB::raw('WEEKDAY(class_sessions.session_date)'), $weekdayIndexes)
            ->where('class_sessions.status', '!=', 'cancelled')
            ->where('course_offerings.campus_id', $campusId)
            ->where(function ($query) use ($startTime, $endTime): void {
                $query
                    ->where(function ($overlap) use ($startTime): void {
                        $overlap->whereTime('class_sessions.start_time', '<=', $startTime)
                            ->whereTime('class_sessions.end_time', '>', $startTime);
                    })
                    ->orWhere(function ($overlap) use ($endTime): void {
                        $overlap->whereTime('class_sessions.start_time', '<', $endTime)
                            ->whereTime('class_sessions.end_time', '>=', $endTime);
                    })
                    ->orWhere(function ($overlap) use ($startTime, $endTime): void {
                        $overlap->whereTime('class_sessions.start_time', '>=', $startTime)
                            ->whereTime('class_sessions.end_time', '<=', $endTime);
                    });
            })
            ->pluck('class_sessions.room_id')
            ->map(static fn (mixed $roomId): int => (int) $roomId)
            ->all();

        return $this->mapRooms(array_values(array_filter(
            $rooms,
            static fn (object $room): bool => ! in_array($room->id, $conflictingRoomIds, true),
        )));
    }

    /** @param list<object> $rooms */
    private function mapRooms(array $rooms): array
    {
        return array_map(static fn (object $room): array => [
            'id' => $room->id,
            'name' => $room->name,
            'code' => $room->code,
            'capacity' => $room->capacity,
            'type' => $room->type,
            'building' => $room->building,
        ], $rooms);
    }
}
