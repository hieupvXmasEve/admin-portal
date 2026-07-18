<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;

final class EloquentProgramEnrollmentReader implements ProgramEnrollmentReader
{
    public function forStudentId(int $studentId): ProgramEnrollmentSummary
    {
        $enrollment = ProgramEnrollment::query()
            ->with([
                'program:id,name,code',
                'curriculumVersion:id,version_code,program_id,specialization_id',
                'curriculumVersion.specialization:id,name,code',
                'intakeSemester:id,code,name,start_date',
                'intakeMajorSemester:id,code,name',
            ])
            ->where('student_id', $studentId)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();

        if ($enrollment !== null) {
            return new ProgramEnrollmentSummary(
                enrollmentId: $enrollment->id,
                programId: $enrollment->program_id,
                programName: $enrollment->program?->name,
                programCode: $enrollment->program?->code,
                curriculumVersionId: $enrollment->curriculum_version_id,
                curriculumVersionCode: $enrollment->curriculumVersion?->version_code,
                specializationId: $enrollment->curriculumVersion?->specialization_id,
                specializationName: $enrollment->curriculumVersion?->specialization?->name,
                specializationCode: $enrollment->curriculumVersion?->specialization?->code,
                intakeSemesterId: $enrollment->intake_semester_id,
                intakeSemesterCode: $enrollment->intakeSemester?->code,
                intakeSemesterName: $enrollment->intakeSemester?->name,
                intakeSemesterStartDate: $enrollment->intakeSemester?->start_date?->toDateString(),
                intakeMajorSemesterId: $enrollment->intake_major_semester_id,
                intakeMajorSemesterCode: $enrollment->intakeMajorSemester?->code,
                intakeMajorSemesterName: $enrollment->intakeMajorSemester?->name,
                enrollmentStatus: $enrollment->enrollment_status,
                studyStage: $enrollment->study_stage,
            );
        }

        return $this->legacySummary($studentId);
    }

    private function legacySummary(int $studentId): ProgramEnrollmentSummary
    {
        $student = Student::query()->findOrFail($studentId);

        $student->loadMissing([
            'program:id,name,code',
            'specialization:id,name,code',
            'curriculumVersion:id,version_code,program_id,specialization_id',
            'curriculumVersion.specialization:id,name,code',
            'intakeSemester:id,code,name,start_date',
            'intakeMajorSemester:id,code,name',
        ]);

        return new ProgramEnrollmentSummary(
            enrollmentId: null,
            programId: $student->program_id,
            programName: $student->program?->name,
            programCode: $student->program?->code,
            curriculumVersionId: $student->curriculum_version_id,
            curriculumVersionCode: $student->curriculumVersion?->version_code,
            specializationId: $student->specialization_id,
            specializationName: $student->specialization?->name,
            specializationCode: $student->specialization?->code,
            intakeSemesterId: $student->intake_semester_id,
            intakeSemesterCode: $student->intakeSemester?->code,
            intakeSemesterName: $student->intakeSemester?->name,
            intakeSemesterStartDate: $student->intakeSemester?->start_date?->toDateString(),
            intakeMajorSemesterId: $student->intakeMajorSemester?->id,
            intakeMajorSemesterCode: $student->intakeMajorSemester?->code,
            intakeMajorSemesterName: $student->intakeMajorSemester?->name,
            enrollmentStatus: $student->academic_status ?? 'active',
            studyStage: $this->legacyStudyStage($student),
        );
    }

    private function legacyStudyStage(Student $student): ?string
    {
        return in_array($student->status, ['intake_pre_uni_gc', 'intake_course', 'intake_major', 'pending_course_opening'], true)
            ? $student->status
            : null;
    }
}
