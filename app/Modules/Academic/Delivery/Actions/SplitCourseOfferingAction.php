<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingSplitException;
use Illuminate\Support\Facades\DB;

final class SplitCourseOfferingAction
{
    /**
     * @param  array{course_offering_id: int, campus_id: int, sections: list<array{section_code: string, max_capacity: int, lecture_id?: int|string|null, location?: string|null, student_ids: list<int>}>}  $data
     */
    public static function run(array $data): int
    {
        $courseOffering = CourseOffering::query()->findOrFail($data['course_offering_id']);
        $campusId = $data['campus_id'];
        $sections = $data['sections'];

        if ((int) $courseOffering->campus_id !== $campusId) {
            throw new CourseOfferingSplitException('Course offering is not available at the current campus.');
        }

        if ($courseOffering->section_code !== null) {
            throw new CourseOfferingSplitException('This course offering is already a section and cannot be split further.');
        }

        if ((int) $courseOffering->current_enrollment === 0) {
            throw new CourseOfferingSplitException('Cannot split a course offering with no enrolled students.');
        }

        $totalStudentsAssigned = collect($sections)->sum(fn (array $section): int => count($section['student_ids']));
        if ($totalStudentsAssigned !== (int) $courseOffering->current_enrollment) {
            throw new CourseOfferingSplitException('All enrolled students must be assigned to sections.');
        }

        DB::transaction(function () use ($courseOffering, $campusId, $sections): void {
            $newOfferings = [];
            $registrationUpdates = [];

            foreach ($sections as $sectionData) {
                $lectureId = isset($sectionData['lecture_id']) && $sectionData['lecture_id'] !== 'none'
                    ? (int) $sectionData['lecture_id']
                    : null;
                $newOffering = CourseOffering::query()->create([
                    'campus_id' => $campusId,
                    'semester_id' => $courseOffering->semester_id,
                    'unit_id' => $courseOffering->unit_id,
                    'section_code' => $sectionData['section_code'],
                    'max_capacity' => $sectionData['max_capacity'],
                    'current_enrollment' => count($sectionData['student_ids']),
                    'waitlist_capacity' => $courseOffering->waitlist_capacity,
                    'current_waitlist' => 0,
                    'delivery_mode' => $courseOffering->delivery_mode,
                    'schedule_days' => $courseOffering->schedule_days,
                    'schedule_time_start' => $courseOffering->schedule_time_start,
                    'schedule_time_end' => $courseOffering->schedule_time_end,
                    'location' => $sectionData['location'] ?? $courseOffering->location,
                    'is_active' => $courseOffering->is_active,
                    'enrollment_status' => $courseOffering->enrollment_status,
                    'registration_start_date' => $courseOffering->registration_start_date,
                    'registration_end_date' => $courseOffering->registration_end_date,
                    'special_requirements' => $courseOffering->special_requirements,
                    'notes' => $courseOffering->notes,
                ]);

                if ($lectureId !== null) {
                    AssignInstructorAction::run([
                        'course_offering_id' => (int) $newOffering->id,
                        'lecture_id' => $lectureId,
                    ]);
                }

                $newOfferings[] = $newOffering;
                foreach ($sectionData['student_ids'] as $studentId) {
                    $registrationUpdates[] = [
                        'student_id' => $studentId,
                        'new_course_offering_id' => $newOffering->id,
                    ];
                }
            }

            foreach ($registrationUpdates as $update) {
                CourseRegistration::query()
                    ->where('course_offering_id', $courseOffering->id)
                    ->where('student_id', $update['student_id'])
                    ->whereIn('registration_status', ['registered', 'confirmed'])
                    ->update(['course_offering_id' => $update['new_course_offering_id']]);
            }

            CourseRegistration::query()
                ->where('course_offering_id', $courseOffering->id)
                ->update(['course_offering_id' => $newOfferings[0]->id]);
            $courseOffering->delete();
        });

        return count($sections);
    }
}
