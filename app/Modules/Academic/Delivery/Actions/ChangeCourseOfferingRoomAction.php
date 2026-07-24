<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingRoomChangeException;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;
use App\Shared\Contracts\Facilities\SpaceAvailabilityReader;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use Illuminate\Support\Facades\DB;

final class ChangeCourseOfferingRoomAction
{
    /** @param array{course_offering_id: int, campus_id: int, room_id: int, requested_by_user_id: int} $data */
    public static function run(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $courseOffering = CourseOffering::query()
                ->whereKey($data['course_offering_id'])
                ->where('campus_id', $data['campus_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $sessions = $courseOffering->classSessions()
                ->orderBy('session_date')
                ->lockForUpdate()
                ->get();

            if ($sessions->isEmpty()) {
                throw new CourseOfferingRoomChangeException('No class sessions exist for this course offering.');
            }

            $room = collect(app(SpaceReferenceReader::class)->forCampus($data['campus_id']))
                ->first(fn (object $space): bool => $space->id === $data['room_id']);

            if ($room === null) {
                throw new CourseOfferingRoomChangeException('Selected room is not available or not in the current campus.');
            }

            foreach ($sessions->where('status', '!=', 'cancelled') as $session) {
                $availability = app(SpaceAvailabilityReader::class)->check(new SpaceReservationRequest(
                    campusId: $data['campus_id'],
                    roomId: $data['room_id'],
                    date: $session->session_date->toDateString(),
                    startTime: $session->start_time->format('H:i'),
                    endTime: $session->end_time->format('H:i'),
                    requestedCapacity: null,
                    title: "Course offering {$courseOffering->id} session",
                    requestedByUserId: $data['requested_by_user_id'],
                ));

                if (! $availability->available) {
                    throw new CourseOfferingRoomChangeException('Selected room is not available for the course offering schedule.');
                }
            }

            $courseOffering->classSessions()->update(['room_id' => $data['room_id']]);
        });
    }
}
