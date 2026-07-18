<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\TeachingEligibilityReader;
use Illuminate\Support\Facades\DB;

/**
 * Delivery-owned assignment decision.
 *
 * Workforce supplies one eligibility fact; this action alone evaluates live
 * Delivery workload, campus alignment, and timetable conflicts before writing
 * the existing assignment reference.
 */
final class AssignInstructorAction
{
    /**
     * @param  array{course_offering_id: int, lecture_id: int}  $data
     */
    public static function run(array $data): CourseOffering
    {
        return DB::transaction(function () use ($data): CourseOffering {
            $offering = CourseOffering::query()
                ->lockForUpdate()
                ->findOrFail($data['course_offering_id']);
            $unit = app(CourseOfferingCatalogReader::class)->offeringUnit((int) $offering->unit_id);
            if ($unit === null) {
                throw new InstructorAssignmentException(
                    'course_offering_id',
                    'The selected course offering has an invalid unit.',
                );
            }

            $eligibility = app(TeachingEligibilityReader::class)
                ->forLecturerAndUnit($data['lecture_id'], $unit);

            if ($eligibility === null) {
                throw new InstructorAssignmentException('lecture_id', 'The selected lecturer does not exist.');
            }

            if (! $eligibility->isEligible) {
                throw new InstructorAssignmentException('lecture_id', 'The selected lecturer is not available for assignment.');
            }

            if ($eligibility->campusId !== (int) $offering->campus_id) {
                throw new InstructorAssignmentException(
                    'lecture_id',
                    'The selected lecturer does not belong to this course offering campus.',
                );
            }

            self::assertWithinWorkload($offering, $eligibility->lecturerId, $eligibility->maxTeachingHoursPerWeek);
            self::assertNoTimetableConflict($offering, $eligibility->lecturerId);

            $offering->update(['lecture_id' => $eligibility->lecturerId]);

            return $offering->fresh();
        });
    }

    private static function assertWithinWorkload(CourseOffering $offering, int $lecturerId, ?int $maxTeachingHoursPerWeek): void
    {
        if ($maxTeachingHoursPerWeek === null) {
            return;
        }

        $currentTeachingMinutes = CourseOffering::query()
            ->where('lecture_id', $lecturerId)
            ->where('semester_id', $offering->semester_id)
            ->where('is_active', true)
            ->whereKeyNot($offering->id)
            ->lockForUpdate()
            ->get(['schedule_time_start', 'schedule_time_end'])
            ->sum(function (CourseOffering $assignedOffering): int {
                if ($assignedOffering->schedule_time_start === null || $assignedOffering->schedule_time_end === null) {
                    return 0;
                }

                return (int) $assignedOffering->schedule_time_start
                    ->diffInMinutes($assignedOffering->schedule_time_end);
            });
        $offeringTeachingMinutes = $offering->schedule_time_start === null || $offering->schedule_time_end === null
            ? 0
            : (int) $offering->schedule_time_start->diffInMinutes($offering->schedule_time_end);

        if (($currentTeachingMinutes + $offeringTeachingMinutes) > $maxTeachingHoursPerWeek * 60) {
            throw new InstructorAssignmentException(
                'lecture_id',
                'The selected lecturer has reached their maximum teaching load.',
            );
        }
    }

    private static function assertNoTimetableConflict(CourseOffering $offering, int $lecturerId): void
    {
        if (
            $offering->schedule_days === null
            || $offering->schedule_days === []
            || $offering->schedule_time_start === null
            || $offering->schedule_time_end === null
        ) {
            return;
        }

        $conflicts = CourseOffering::query()
            ->where('lecture_id', $lecturerId)
            ->where('semester_id', $offering->semester_id)
            ->where('is_active', true)
            ->whereKeyNot($offering->id)
            ->where(function ($query) use ($offering): void {
                foreach ($offering->schedule_days as $day) {
                    $query->orWhereJsonContains('schedule_days', $day);
                }
            })
            ->where('schedule_time_start', '<', $offering->schedule_time_end)
            ->where('schedule_time_end', '>', $offering->schedule_time_start)
            ->exists();

        if ($conflicts) {
            throw new InstructorAssignmentException(
                'lecture_id',
                'The selected lecturer has schedule conflicts with this course offering.',
            );
        }
    }
}
