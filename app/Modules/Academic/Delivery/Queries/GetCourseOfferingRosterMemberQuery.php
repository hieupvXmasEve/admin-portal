<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

final readonly class GetCourseOfferingRosterMemberQuery
{
    public function __construct(private StudentReferenceReader $students) {}

    /**
     * @return array{registration_id: int, student_id: int, student_name: string, student_code: string}|null
     */
    public function handle(CourseOffering $courseOffering, int $courseRegistrationId): ?array
    {
        $registration = CourseRegistration::query()
            ->whereKey($courseRegistrationId)
            ->where('course_offering_id', $courseOffering->id)
            ->first();
        if ($registration === null) {
            return null;
        }

        $student = $this->students->find((int) $registration->student_id);

        return [
            'registration_id' => (int) $registration->id,
            'student_id' => (int) $registration->student_id,
            'student_name' => $student?->fullName ?? 'Unknown Student',
            'student_code' => $student?->studentCode ?? 'Unknown ID',
        ];
    }
}
