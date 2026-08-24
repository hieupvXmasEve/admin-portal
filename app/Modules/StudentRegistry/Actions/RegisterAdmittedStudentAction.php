<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\DTO\AdmittedStudentIdentity;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;

final class RegisterAdmittedStudentAction
{
    /** @param array{identity: AdmittedStudentIdentity} $data */
    public static function run(array $data): StudentReference
    {
        $identity = $data['identity'];
        $student = Student::query()->create([
            'student_id' => $identity->studentCode,
            'user_id' => $identity->accountId,
            'full_name' => $identity->fullName,
            'email' => $identity->email,
            'campus_id' => $identity->campusId,
            'program_id' => $identity->programId,
            'curriculum_version_id' => $identity->curriculumVersionId,
            'specialization_id' => $identity->specializationId,
            'intake_semester_id' => $identity->intakeSemesterId,
            'admission_date' => $identity->admissionDate,
            'expected_graduation_date' => $identity->expectedGraduationDate,
            'phone' => $identity->phone,
            'date_of_birth' => $identity->dateOfBirth,
            'gender' => $identity->gender,
            'nationality' => $identity->nationality,
            'national_id' => $identity->nationalId,
            'address' => $identity->address,
            'current_address_line' => $identity->currentAddressLine,
            'current_ward' => $identity->currentWard,
            'current_province' => $identity->currentProvince,
            'cccd_address' => $identity->cccdAddress,
            'emergency_contact_name' => $identity->emergencyContactName,
            'emergency_contact_phone' => $identity->emergencyContactPhone,
            'emergency_contact_relationship' => $identity->emergencyContactRelationship,
            'high_school_name' => $identity->highSchoolName,
            'admission_notes' => $identity->admissionNotes,
            'status' => 'active',
            'academic_status' => 'active',
            // 0 = "cohort not declared" sentinel; real cohorts come from the
            // CRM mapping screen's intake config and start at 1.
            'intake' => $identity->cohort ?? 0,
        ]);

        return new StudentReference(
            id: (int) $student->id,
            studentCode: (string) $student->student_id,
            fullName: (string) $student->full_name,
            campusId: (int) $student->campus_id,
            email: $student->email,
            address: $student->current_address_line ?? $student->address,
            nationalId: $student->national_id,
            userId: (int) $student->user_id,
        );
    }
}
