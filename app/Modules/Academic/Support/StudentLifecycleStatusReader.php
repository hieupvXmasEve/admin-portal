<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader as StudentLifecycleStatusReaderContract;

final class StudentLifecycleStatusReader implements StudentLifecycleStatusReaderContract
{
    public function statusesFor(array $studentIds): array
    {
        $statuses = ProgramEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_primary', true)
            ->orderBy('id')
            ->get(['student_id', 'enrollment_status', 'study_stage'])
            ->mapWithKeys(static fn (ProgramEnrollment $enrollment): array => [
                (int) $enrollment->student_id => self::legacyStatus($enrollment),
            ])
            ->all();

        $missingIds = array_values(array_diff($studentIds, array_keys($statuses)));
        if ($missingIds === []) {
            return $statuses;
        }

        return $statuses + Student::query()
            ->whereKey($missingIds)
            ->pluck('status', 'id')
            ->mapWithKeys(static fn (mixed $status, int|string $studentId): array => [
                (int) $studentId => (string) $status,
            ])
            ->all();
    }

    public function academicStatusesFor(array $studentIds): array
    {
        $statuses = ProgramEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_primary', true)
            ->orderBy('id')
            ->pluck('enrollment_status', 'student_id')
            ->mapWithKeys(static fn (mixed $status, int|string $studentId): array => [
                (int) $studentId => $status !== null ? (string) $status : null,
            ])
            ->all();

        $missingIds = array_values(array_diff($studentIds, array_keys($statuses)));
        if ($missingIds === []) {
            return $statuses;
        }

        return $statuses + Student::query()
            ->whereKey($missingIds)
            ->pluck('academic_status', 'id')
            ->mapWithKeys(static fn (mixed $status, int|string $studentId): array => [
                (int) $studentId => $status !== null ? (string) $status : null,
            ])
            ->all();
    }

    private static function legacyStatus(ProgramEnrollment $enrollment): string
    {
        if ($enrollment->enrollment_status === 'active') {
            return $enrollment->study_stage ?? 'active';
        }

        return match ($enrollment->enrollment_status) {
            'withdrawn' => 'dropout',
            default => $enrollment->enrollment_status,
        };
    }
}
