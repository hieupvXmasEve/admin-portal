<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseRegistration;
use App\Shared\Contracts\Academic\DTO\StudentHubRegistrationEvidence;
use App\Shared\Contracts\Academic\StudentHubRegistrationEvidenceReader;

final class EloquentStudentHubRegistrationEvidenceReader implements StudentHubRegistrationEvidenceReader
{
    public function forStudent(int $studentId): array
    {
        return CourseRegistration::query()
            ->with([
                'courseOffering.unit:id,name,code,credit_points',
                'courseOffering.semester:id,name,code,start_date,end_date',
                'semester:id,name,code,start_date,end_date',
            ])
            ->where('student_id', $studentId)
            ->orderByDesc('registration_date')
            ->get()
            ->map(static function (CourseRegistration $registration): StudentHubRegistrationEvidence {
                $semester = $registration->semester ?? $registration->courseOffering?->semester;
                $unit = $registration->courseOffering?->unit;

                return new StudentHubRegistrationEvidence(
                    id: (int) $registration->id,
                    courseOfferingId: (int) $registration->course_offering_id,
                    semesterId: $registration->semester_id === null ? null : (int) $registration->semester_id,
                    unitId: $unit?->id === null ? null : (int) $unit->id,
                    courseName: (string) ($unit?->name ?? 'N/A'),
                    courseCode: (string) ($unit?->code ?? 'N/A'),
                    sectionCode: (string) ($registration->courseOffering?->section_code ?? 'N/A'),
                    unitCreditPoints: (float) ($unit?->credit_points ?? 0),
                    semesterName: (string) ($semester?->name ?? 'N/A'),
                    semesterCode: (string) ($semester?->code ?? 'N/A'),
                    semesterStartDate: $semester?->start_date?->toDateString(),
                    semesterEndDate: $semester?->end_date?->toDateString(),
                    registrationStatus: (string) $registration->registration_status,
                    registrationDate: $registration->registration_date?->toDateString(),
                    registrationMethod: $registration->registration_method,
                    completionDate: $registration->completion_date?->toDateString(),
                    dropDate: $registration->drop_date?->toDateString(),
                    withdrawalDate: $registration->withdrawal_date?->toDateString(),
                    retakeFee: (float) ($registration->retake_fee ?? 0),
                    isRetakePaid: (string) ($registration->is_retake_paid ?? 'no'),
                    notes: $registration->notes,
                    attemptNumber: $registration->attempt_number === null ? null : (int) $registration->attempt_number,
                    isRetake: (bool) $registration->is_retake,
                    creditPoints: (float) ($registration->credit_points ?? 0),
                    finalGrade: $registration->final_grade,
                    gradePoints: $registration->grade_points === null ? null : (float) $registration->grade_points,
                );
            })
            ->all();
    }
}
