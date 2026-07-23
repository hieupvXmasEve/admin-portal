<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Actions;

use App\Models\Student;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use Illuminate\Support\Facades\DB;

final class UpdateStudentIdentityEmailAction
{
    /** @param array{student_id: int, email: string} $data */
    public static function run(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $student = Student::query()->findOrFail($data['student_id']);
            $student->update(['email' => $data['email']]);

            if ($student->user_id !== null) {
                app(StudentAccessWriter::class)->updateEmail((int) $student->user_id, $data['email']);
            }
        });
    }
}
