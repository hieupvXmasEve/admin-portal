<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentProfile;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentProfilePersistenceWriter;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentSerializedReferenceReader;
use App\Shared\Support\Academic\StudentLifecycleProjection;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentStudentRegistryStore implements StudentProfilePersistenceWriter, StudentProfileReader, StudentReferenceReader, StudentSerializedReferenceReader
{
    public function __construct(
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    public function update(int $studentId, array $attributes): bool
    {
        return Student::query()->findOrFail($studentId)->update($attributes);
    }

    public function find(int $studentId): ?StudentReference
    {
        $student = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->find($studentId);

        if ($student === null) {
            return null;
        }

        return $this->reference($student, $this->resolveOne((int) $student->id));
    }

    public function findSerialized(int $studentId): ?array
    {
        $student = Student::query()->find($studentId);
        if ($student === null) {
            return null;
        }

        $data = $student->toArray();
        $data['status'] = $this->resolveOne((int) $student->id) ?? $student->status;

        return $data;
    }

    public function findManySerialized(array $studentIds): array
    {
        $students = Student::query()
            ->whereIn('id', array_values(array_unique($studentIds)))
            ->get();

        $statuses = $this->resolveMany($students);

        return $students
            ->mapWithKeys(fn (Student $student): array => [
                (int) $student->id => [
                    ...$student->toArray(),
                    'status' => $statuses[(int) $student->id] ?? $student->status,
                ],
            ])
            ->all();
    }

    public function findProfile(int $studentId): ?StudentProfile
    {
        $student = Student::query()
            ->select([
                'id',
                'student_id',
                'user_id',
                'full_name',
                'campus_id',
                'phone',
                'date_of_birth',
                'gender',
                'nationality',
                'ethnicity',
                'avatar_url',
                'national_id',
                'address',
                'current_address_line',
                'current_ward',
                'current_province',
                'current_country',
                'cccd_address',
                'cccd_address_line',
                'cccd_ward',
                'cccd_province',
                'cccd_country',
                'email',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'emergency_contact_email',
                'emergency_contact_name_1',
                'emergency_contact_email_1',
                'emergency_contact_phone_1',
                'emergency_contact_relationship_1',
                'high_school_name',
                'admission_date',
                'expected_graduation_date',
                'status_change_date',
                'status_reason',
                'intake_mode',
                'high_school_graduation_year',
                'entrance_exam_score',
                'admission_notes',
            ])
            ->find($studentId);

        return $student === null ? null : new StudentProfile(
            id: (int) $student->id,
            studentCode: (string) $student->student_id,
            userId: $student->user_id === null ? null : (int) $student->user_id,
            fullName: (string) $student->full_name,
            campusId: (int) $student->campus_id,
            phone: $student->phone,
            dateOfBirth: $student->date_of_birth?->toDateString(),
            gender: $student->gender,
            nationality: $student->nationality,
            ethnicity: $student->ethnicity,
            avatarUrl: $student->avatar_url,
            nationalId: $student->national_id,
            address: $student->address,
            currentAddressLine: $student->current_address_line,
            currentWard: $student->current_ward,
            currentProvince: $student->current_province,
            currentCountry: $student->current_country,
            cccdAddress: $student->cccd_address,
            cccdAddressLine: $student->cccd_address_line,
            cccdWard: $student->cccd_ward,
            cccdProvince: $student->cccd_province,
            cccdCountry: $student->cccd_country,
            email: $student->email,
            emergencyContactName: $student->emergency_contact_name,
            emergencyContactPhone: $student->emergency_contact_phone,
            emergencyContactRelationship: $student->emergency_contact_relationship,
            emergencyContactEmail: $student->emergency_contact_email,
            emergencyContactName1: $student->emergency_contact_name_1,
            emergencyContactEmail1: $student->emergency_contact_email_1,
            emergencyContactPhone1: $student->emergency_contact_phone_1,
            emergencyContactRelationship1: $student->emergency_contact_relationship_1,
            highSchoolName: $student->high_school_name,
            admissionDate: $student->admission_date?->toDateString(),
            expectedGraduationDate: $student->expected_graduation_date?->toDateString(),
            statusChangeDate: $student->status_change_date?->toDateString(),
            statusReason: $student->status_reason,
            intakeMode: $student->intake_mode,
            highSchoolGraduationYear: $student->high_school_graduation_year === null ? null : (int) $student->high_school_graduation_year,
            entranceExamScore: $student->entrance_exam_score === null ? null : (float) $student->entrance_exam_score,
            admissionNotes: $student->admission_notes,
        );
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, StudentReference>
     */
    public function findMany(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $students = Student::query()
            ->leftJoin('programs as registry_programs', 'registry_programs.id', '=', 'students.program_id')
            ->leftJoin('specializations as registry_specializations', 'registry_specializations.id', '=', 'students.specialization_id')
            ->select(array_map(static fn (string $column): string => 'students.'.$column, $this->referenceColumns()))
            ->addSelect([
                'registry_programs.code as registry_program_code',
                'registry_programs.name as registry_program_name',
                'registry_specializations.code as registry_specialization_code',
                'registry_specializations.name as registry_specialization_name',
            ])
            ->whereIn('students.id', $studentIds)
            ->get();

        $statuses = $this->resolveMany($students);

        return $students
            ->mapWithKeys(fn (Student $student): array => [
                (int) $student->id => $this->joinedReference($student, $statuses[(int) $student->id] ?? null),
            ])
            ->all();
    }

    public function findByStudentCode(string $studentCode, int $campusId): ?StudentReference
    {
        $student = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->where('student_id', $studentCode)
            ->where('campus_id', $campusId)
            ->first();

        if ($student === null) {
            return null;
        }

        return $this->reference($student, $this->resolveOne((int) $student->id));
    }

    public function findByStudentCodeAnywhere(string $studentCode): ?StudentReference
    {
        $student = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->where('student_id', $studentCode)
            ->first();

        if ($student === null) {
            return null;
        }

        return $this->reference($student, $this->resolveOne((int) $student->id));
    }

    /**
     * Batch resolution for callers that would otherwise call
     * `findByStudentCode()` once per code inside a loop (H7).
     *
     * @param  list<string>  $studentCodes
     * @return array<string, StudentReference>
     */
    public function findManyByStudentCode(array $studentCodes, int $campusId): array
    {
        $codes = array_values(array_unique($studentCodes));
        if ($codes === []) {
            return [];
        }

        $students = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->where('campus_id', $campusId)
            ->whereIn('student_id', $codes)
            ->get();

        $statuses = $this->resolveMany($students);

        return $students
            ->mapWithKeys(fn (Student $student): array => [
                (string) $student->student_id => $this->reference($student, $statuses[(int) $student->id] ?? null),
            ])
            ->all();
    }

    /** @return list<int> */
    public function idsForCampus(?int $campusId): array
    {
        return Student::query()
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /** @return iterable<StudentReference> */
    public function stream(?int $campusId = null): iterable
    {
        $students = Student::query()
            ->leftJoin('programs as registry_programs', 'registry_programs.id', '=', 'students.program_id')
            ->leftJoin('specializations as registry_specializations', 'registry_specializations.id', '=', 'students.specialization_id')
            ->select(array_map(static fn (string $column): string => 'students.'.$column, $this->referenceColumns()))
            ->addSelect([
                'registry_programs.code as registry_program_code',
                'registry_programs.name as registry_program_name',
                'registry_specializations.code as registry_specialization_code',
                'registry_specializations.name as registry_specialization_name',
            ])
            ->when($campusId !== null, fn ($query) => $query->where('students.campus_id', $campusId))
            ->orderBy('students.student_id')
            ->orderBy('students.id')
            ->lazy(500);

        // Resolve per 500-row chunk (matching the underlying `lazy(500)`
        // cursor size) instead of per row, so streaming the whole registry
        // does not issue one `program_enrollments` query per student.
        foreach ($students->chunk(500) as $chunk) {
            $statuses = $this->resolveMany($chunk);
            foreach ($chunk as $student) {
                yield $this->joinedReference($student, $statuses[(int) $student->id] ?? null);
            }
        }
    }

    /**
     * @return list<int>
     */
    public function activeIdsForCampus(?int $campusId = null): array
    {
        $query = Student::query();
        StudentLifecycleProjection::whereStatusIn($query, ['intake_pre_uni_gc', 'intake_course']);

        return $query
            ->when($campusId !== null, fn (Builder $students) => $students->where('campus_id', $campusId))
            ->pluck('id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->all();
    }

    /** @return list<int> */
    public function idsMatchingSearch(string $query, ?int $campusId = null): array
    {
        return Student::query()
            ->when($campusId !== null, fn (Builder $students) => $students->where('campus_id', $campusId))
            ->where(function (Builder $students) use ($query): void {
                $students->where('student_id', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /** @return list<StudentReference> */
    public function search(string $query, int $campusId, int $limit = 10): array
    {
        $students = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->where('campus_id', $campusId)
            ->where(function (Builder $students) use ($query): void {
                $students->where('student_id', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get();

        $statuses = $this->resolveMany($students);

        return $students
            ->map(fn (Student $student): StudentReference => $this->reference($student, $statuses[(int) $student->id] ?? null))
            ->all();
    }

    /**
     * `students.status` is write-dead; the resolved status is the
     * Progression-owned projection (falling back to the column for a student
     * with no primary enrollment), always supplied by the caller via
     * `resolveOne()`/`resolveMany()` — never re-derived here.
     */
    private function reference(Student $student, ?string $resolvedStatus = null): StudentReference
    {
        $status = $resolvedStatus ?? $student->status;

        return new StudentReference(
            id: (int) $student->id,
            studentCode: (string) $student->student_id,
            fullName: (string) $student->full_name,
            campusId: (int) $student->campus_id,
            email: $student->email === null ? null : (string) $student->email,
            address: $student->current_address_line ?? $student->address,
            nationalId: $student->national_id === null ? null : (string) $student->national_id,
            userId: $student->user_id === null ? null : (int) $student->user_id,
            programId: $student->program_id === null ? null : (int) $student->program_id,
            programCode: $student->program?->code,
            programName: $student->program?->name,
            specializationId: $student->specialization_id === null ? null : (int) $student->specialization_id,
            specializationCode: $student->specialization?->code,
            specializationName: $student->specialization?->name,
            status: $status,
            academicStatus: $student->academic_status,
            gcCurrentLevel: $student->gc_current_level === null ? null : (int) $student->gc_current_level,
            statusLabel: StudentLifecycleStatusPresenter::label($status),
            intakeSemesterId: $student->intake_semester_id === null ? null : (int) $student->intake_semester_id,
            cohort: $student->intake === null ? null : (int) $student->intake,
            curriculumVersionId: $student->curriculum_version_id === null ? null : (int) $student->curriculum_version_id,
        );
    }

    private function joinedReference(Student $student, ?string $resolvedStatus = null): StudentReference
    {
        $status = $resolvedStatus ?? $student->status;

        return new StudentReference(
            id: (int) $student->id,
            studentCode: (string) $student->student_id,
            fullName: (string) $student->full_name,
            campusId: (int) $student->campus_id,
            email: $student->email === null ? null : (string) $student->email,
            address: $student->current_address_line ?? $student->address,
            nationalId: $student->national_id === null ? null : (string) $student->national_id,
            userId: $student->user_id === null ? null : (int) $student->user_id,
            programId: $student->program_id === null ? null : (int) $student->program_id,
            programCode: $student->registry_program_code,
            programName: $student->registry_program_name,
            specializationId: $student->specialization_id === null ? null : (int) $student->specialization_id,
            specializationCode: $student->registry_specialization_code,
            specializationName: $student->registry_specialization_name,
            status: $status,
            academicStatus: $student->academic_status,
            gcCurrentLevel: $student->gc_current_level === null ? null : (int) $student->gc_current_level,
            statusLabel: StudentLifecycleStatusPresenter::label($status),
            intakeSemesterId: $student->intake_semester_id === null ? null : (int) $student->intake_semester_id,
            cohort: $student->intake === null ? null : (int) $student->intake,
            curriculumVersionId: $student->curriculum_version_id === null ? null : (int) $student->curriculum_version_id,
        );
    }

    private function resolveOne(int $studentId): ?string
    {
        return $this->lifecycleStatuses->statusesFor([$studentId])[$studentId] ?? null;
    }

    /**
     * @param  iterable<int, Student>  $students
     * @return array<int, string>
     */
    private function resolveMany(iterable $students): array
    {
        $ids = (new Collection($students))
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        return $this->lifecycleStatuses->statusesFor($ids);
    }

    /** @return list<string> */
    private function referenceColumns(): array
    {
        return [
            'id',
            'student_id',
            'user_id',
            'full_name',
            'campus_id',
            'email',
            'current_address_line',
            'address',
            'national_id',
            'program_id',
            'specialization_id',
            'status',
            'academic_status',
            'gc_current_level',
            'intake_semester_id',
            'intake',
            'curriculum_version_id',
        ];
    }
}
