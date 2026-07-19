<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EloquentProgramEnrollmentReader implements ProgramEnrollmentReader
{
    public function forStudentId(int $studentId): ProgramEnrollmentSummary
    {
        return $this->forStudentIds([$studentId])[$studentId];
    }

    public function forStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if ($studentIds === []) {
            return [];
        }

        $enrollments = ProgramEnrollment::query()
            ->with([
                'program:id,name,code',
                'curriculumVersion:id,version_code,program_id,specialization_id',
                'curriculumVersion.specialization:id,name,code',
                'intakeSemester:id,code,name,start_date',
                'intakeMajorSemester:id,code,name',
            ])
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');

        $lifecycleLogs = StudentActionLog::query()
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('new_status')
            ->orderByDesc('id')
            ->get(['student_id', 'new_status', 'created_at'])
            ->groupBy('student_id');

        $missingStudentIds = array_values(array_diff(
            $studentIds,
            $enrollments->keys()->map(static fn (int|string $id): int => (int) $id)->all(),
        ));
        $legacyStudents = Student::query()
            ->with([
                'program:id,name,code',
                'specialization:id,name,code',
                'curriculumVersion:id,version_code,program_id,specialization_id',
                'curriculumVersion.specialization:id,name,code',
                'intakeSemester:id,code,name,start_date',
                'intakeMajorSemester:id,code,name',
            ])
            ->whereIn('id', $missingStudentIds)
            ->get()
            ->keyBy('id');

        $summaries = [];
        foreach ($studentIds as $studentId) {
            /** @var ProgramEnrollment|null $enrollment */
            $enrollment = $enrollments->get($studentId);
            if ($enrollment !== null) {
                $latestLifecycleStatus = $lifecycleLogs
                    ->get($studentId, collect())
                    ->first(static fn (StudentActionLog $log): bool => $enrollment->materialized_at === null
                        || $log->created_at->greaterThanOrEqualTo($enrollment->materialized_at))
                    ?->new_status;
                $summaries[$studentId] = $this->enrollmentSummary(
                    $enrollment,
                    $latestLifecycleStatus,
                );

                continue;
            }

            /** @var Student|null $student */
            $student = $legacyStudents->get($studentId);
            if ($student === null) {
                throw (new ModelNotFoundException)->setModel(Student::class, [$studentId]);
            }

            $summaries[$studentId] = $this->legacySummary($student);
        }

        return $summaries;
    }

    private function enrollmentSummary(
        ProgramEnrollment $enrollment,
        ?string $latestLifecycleStatus,
    ): ProgramEnrollmentSummary {
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
            legacyLifecycleStatus: $this->legacyLifecycleStatus($enrollment, $latestLifecycleStatus),
            egcStartingLevel: $enrollment->egc_starting_level,
            egcCurrentLevel: $enrollment->egc_current_level,
            egcTotalLevels: $enrollment->egc_total_levels,
        );
    }

    private function legacySummary(Student $student): ProgramEnrollmentSummary
    {
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
            legacyLifecycleStatus: $student->status,
            egcStartingLevel: $student->gc_starting_level,
            egcCurrentLevel: $student->gc_current_level,
            egcTotalLevels: $student->gc_total_levels,
        );
    }

    private function legacyLifecycleStatus(
        ProgramEnrollment $enrollment,
        ?string $latestLifecycleStatus,
    ): string {
        if ($enrollment->enrollment_status === 'active') {
            return $enrollment->study_stage ?? 'active';
        }

        $sourceStatus = $enrollment->source_snapshot['status'] ?? null;

        return match ($enrollment->enrollment_status) {
            'withdrawn' => in_array($latestLifecycleStatus, ['dropout', 'dropout_transfer'], true)
                ? $latestLifecycleStatus
                : (in_array($sourceStatus, ['dropout', 'dropout_transfer'], true) ? $sourceStatus : 'dropout'),
            'deferred' => in_array($latestLifecycleStatus, ['deferred', 'admission_deferred'], true)
                ? $latestLifecycleStatus
                : (in_array($sourceStatus, ['deferred', 'admission_deferred'], true) ? $sourceStatus : 'deferred'),
            default => $enrollment->enrollment_status,
        };
    }

    private function legacyStudyStage(Student $student): ?string
    {
        return in_array($student->status, ['intake_pre_uni_gc', 'intake_course', 'intake_major', 'pending_course_opening'], true)
            ? $student->status
            : null;
    }
}
