<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Observers;

use App\Shared\Contracts\StudentRegistry\StudentRegistered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class PublishStudentRegistered
{
    public function created(object $student): void
    {
        $event = new StudentRegistered(
            studentId: (int) $student->id,
            campusId: (int) $student->campus_id,
            userId: $student->user_id === null ? null : (int) $student->user_id,
        );

        DB::afterCommit(static fn (): mixed => Event::dispatch($event));
    }
}
