<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Attendance;

use App\Models\ClassSession;

/** @deprecated Resolve the Delivery action instead. */
class BulkUpdateClassSessionAttendanceAction
{
    /** @param array{class_session: ClassSession, attendance_ids: array<int, int>, status: string} $data */
    public static function run(array $data): int
    {
        return \App\Modules\Academic\Delivery\Actions\BulkUpdateClassSessionAttendanceAction::run($data);
    }
}
