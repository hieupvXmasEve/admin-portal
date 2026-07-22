<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Modules\StudentRegistry\Actions\UpdateStudentProfileAction;
use App\Shared\Contracts\StudentRegistry\StudentProfileWriter;

final class EloquentStudentProfileWriter implements StudentProfileWriter
{
    public function update(int $studentId, array $attributes): bool
    {
        return UpdateStudentProfileAction::run([
            'student_id' => $studentId,
            'attributes' => $attributes,
        ]);
    }
}
