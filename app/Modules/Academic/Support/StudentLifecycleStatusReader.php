<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\Student;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader as StudentLifecycleStatusReaderContract;

final class StudentLifecycleStatusReader implements StudentLifecycleStatusReaderContract
{
    public function statusesFor(array $studentIds): array
    {
        return Student::query()
            ->whereKey($studentIds)
            ->pluck('status', 'id')
            ->mapWithKeys(static fn (mixed $status, int|string $studentId): array => [
                (int) $studentId => (string) $status,
            ])
            ->all();
    }

    public function academicStatusesFor(array $studentIds): array
    {
        return Student::query()
            ->whereKey($studentIds)
            ->pluck('academic_status', 'id')
            ->mapWithKeys(static fn (mixed $status, int|string $studentId): array => [
                (int) $studentId => $status !== null ? (string) $status : null,
            ])
            ->all();
    }
}
