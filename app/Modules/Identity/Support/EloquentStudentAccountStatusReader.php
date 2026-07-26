<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Models\User;
use App\Shared\Contracts\Identity\StudentAccountStatusReader;

final class EloquentStudentAccountStatusReader implements StudentAccountStatusReader
{
    public function hasActiveAccountForStudent(int $studentId): bool
    {
        return User::query()
            ->whereHas('student', static fn ($student) => $student->whereKey($studentId))
            ->where('status', User::STATUS_ACTIVE)
            ->exists();
    }
}
