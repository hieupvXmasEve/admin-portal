<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentReferenceReader implements StudentReferenceReader
{
    public function find(int $studentId): ?StudentReference
    {
        $student = Student::query()
            ->select(['id', 'student_id', 'user_id', 'full_name', 'campus_id', 'email', 'current_address_line', 'address', 'national_id'])
            ->find($studentId);

        return $student !== null ? $this->reference($student) : null;
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
            ->select(['id', 'student_id', 'user_id', 'full_name', 'campus_id', 'email', 'current_address_line', 'address', 'national_id'])
            ->whereIn('id', $studentIds)
            ->get()
            ->mapWithKeys(fn (Student $student): array => [(int) $student->id => $this->reference($student)])
            ->all();
    }

    public function findByStudentCode(string $studentCode, int $campusId): ?StudentReference
    {
        $student = Student::query()
            ->select(['id', 'student_id', 'user_id', 'full_name', 'campus_id', 'email', 'current_address_line', 'address', 'national_id'])
            ->where('student_id', $studentCode)
            ->where('campus_id', $campusId)
            ->first();

        return $student !== null ? $this->reference($student) : null;
    }

    public function findByStudentCodeAnywhere(string $studentCode): ?StudentReference
    {
        $student = Student::query()
            ->select(['id', 'student_id', 'user_id', 'full_name', 'campus_id', 'email', 'current_address_line', 'address', 'national_id'])
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

    /**
     * @return list<StudentReference>
     */
    public function search(string $query, int $campusId): array
    {
        return Student::query()
            ->select(['id', 'student_id', 'user_id', 'full_name', 'campus_id', 'email', 'current_address_line', 'address', 'national_id'])
            ->where('campus_id', $campusId)
            ->where(function (Builder $students) use ($query): void {
                $students->where('student_id', 'like', "%{$query}%")
                    ->orWhere('full_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('full_name')
            ->limit(10)
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
        );
    }
}
