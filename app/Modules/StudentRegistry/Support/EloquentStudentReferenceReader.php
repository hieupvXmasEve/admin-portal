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
            ->select(['id', 'student_id', 'full_name', 'campus_id'])
            ->find($studentId);

        return $student !== null ? $this->reference($student) : null;
    }

    public function findByStudentCode(string $studentCode, int $campusId): ?StudentReference
    {
        $student = Student::query()
            ->select(['id', 'student_id', 'full_name', 'campus_id'])
            ->where('student_id', $studentCode)
            ->where('campus_id', $campusId)
            ->first();

        return $student !== null ? $this->reference($student) : null;
    }

    /**
     * @return list<StudentReference>
     */
    public function search(string $query, int $campusId): array
    {
        return Student::query()
            ->select(['id', 'student_id', 'full_name', 'campus_id'])
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
        );
    }
}
