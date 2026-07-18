<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Models\Student;

final class RevokeStudentIdentityAction
{
    /** @param array{student_id: int} $data */
    public static function run(array $data): void
    {
        Student::query()->find($data['student_id'])?->delete();
    }
}
