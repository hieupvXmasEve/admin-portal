<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\DTO\StudentProfile;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentProfilePersistenceWriter;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentRegistryStore implements StudentProfilePersistenceWriter, StudentProfileReader, StudentReferenceReader
{
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

        return $student !== null ? $this->reference($student) : null;
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

        return Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->whereIn('id', $studentIds)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [(int) $student->id => $this->reference($student)])
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

        return $student !== null ? $this->reference($student) : null;
    }

    public function findByStudentCodeAnywhere(string $studentCode): ?StudentReference
    {
        $student = Student::query()
            ->with(['program:id,code,name', 'specialization:id,code,name'])
            ->select($this->referenceColumns())
            ->where('student_id', $studentCode)
            ->first();

        return $student !== null ? $this->reference($student) : null;
    }

    /** @return list<int> */
    public function idsForCampus(int $campusId): array
    {
        return Student::query()
            ->where('campus_id', $campusId)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function activeIdsForCampus(?int $campusId = null): array
    {
        return Student::query()
            ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
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
        return Student::query()
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
            ->get()
            ->map(fn (Student $student): StudentReference => $this->reference($student))
            ->all();
    }

    private function reference(Student $student): StudentReference
    {
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
            status: $student->status,
            academicStatus: $student->academic_status,
            gcCurrentLevel: $student->gc_current_level === null ? null : (int) $student->gc_current_level,
        );
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
        ];
    }
}
