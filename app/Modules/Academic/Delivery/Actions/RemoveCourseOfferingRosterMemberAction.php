<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\CourseOfferingAttemptWriter;
use Illuminate\Support\Facades\DB;

final class RemoveCourseOfferingRosterMemberAction
{
    public function __construct(private readonly CourseOfferingAttemptWriter $courseOfferingAttempts) {}

    /** @param array{course_registration_id: int, campus_id: int} $data */
    public static function run(array $data): CourseRegistration
    {
        return app(self::class)->handle($data);
    }

    /** @param array{course_registration_id: int, campus_id: int} $data */
    public function handle(array $data): CourseRegistration
    {
        return DB::transaction(function () use ($data): CourseRegistration {
            $registration = CourseRegistration::query()
                ->lockForUpdate()
                ->findOrFail($data['course_registration_id']);

            $this->courseOfferingAttempts->removeForOffering(
                (int) $registration->student_id,
                (int) $registration->course_offering_id,
            );

            RemoveStudentFromCourseOfferingAction::run((int) $registration->id, $data['campus_id']);

            return $registration;
        });
    }
}
