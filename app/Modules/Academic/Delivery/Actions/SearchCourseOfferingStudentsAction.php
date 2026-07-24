<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\CourseRosterReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

final class SearchCourseOfferingStudentsAction
{
    /** @param list<string> $studentCodes @return list<array<string,mixed>> */
    public static function run(object $courseOffering, array $studentCodes, int $campusId): array
    {
        $unit = app(CourseOfferingCatalogReader::class)->offeringUnit((int) $courseOffering->unit_id);
        $students = app(StudentReferenceReader::class);
        $results = [];

        foreach ($studentCodes as $studentCode) {
            $student = $students->findByStudentCode($studentCode, $campusId);
            $result = [
                'student_id' => $studentCode,
                'exists' => $student !== null,
                'is_eligible' => false,
                'is_already_registered' => false,
                'eligibility_reasons' => [],
            ];

            if ($student === null) {
                $result['eligibility_reasons'][] = 'Student ID not found in system';
                $result['major_code'] = 'N/A';
                $results[] = $result;

                continue;
            }

            $result['student_data'] = [
                'id' => $student->id,
                'student_id' => $student->studentCode,
                'full_name' => $student->fullName,
                'email' => $student->email,
                'program' => $student->programCode === null ? null : ['code' => $student->programCode, 'name' => $student->programName],
                'specialization' => $student->specializationCode === null ? null : ['code' => $student->specializationCode, 'name' => $student->specializationName],
            ];
            $result['major_code'] = $student->specializationCode ?? $student->programCode ?? 'N/A';

            if (app(CourseRosterReader::class)->studentHasVisibleRegistration($student->id, (int) $courseOffering->id)) {
                $result['is_already_registered'] = true;
                $result['eligibility_reasons'][] = 'Already registered for this course offering';
                $results[] = $result;

                continue;
            }

            $reasons = [];
            if ($unit === null) {
                $reasons[] = 'The course offering has an invalid unit.';
            } elseif ($unit->unit_type === 'egc' && $student->gcCurrentLevel !== $unit->level) {
                $reasons[] = $student->gcCurrentLevel === null ? "Student's GC level is not set" : "Student's current GC level ({$student->gcCurrentLevel}) does not match unit level ({$unit->level})";
            }
            if ($student->status !== null && ! in_array($student->status, $unit?->unit_type === 'egc' ? ['intake_pre_uni_gc'] : ['intake_course'], true)) {
                $reasons[] = "This unit requires a compatible student type (current: '{$student->status}')";
            }
            if ($student->academicStatus !== null && $student->academicStatus !== 'active') {
                $reasons[] = "Student status is '{$student->academicStatus}' (must be 'active')";
            }
            if ($courseOffering->isFull()) {
                $reasons[] = 'Course offering is at full capacity';
            }
            if (! $courseOffering->isRegistrationOpen()) {
                $reasons[] = 'Registration period is not currently open';
            }

            $result['is_eligible'] = $reasons === [];
            $result['eligibility_reasons'] = $reasons === [] ? ['Eligible for registration'] : $reasons;
            $results[] = $result;
        }

        return $results;
    }
}
