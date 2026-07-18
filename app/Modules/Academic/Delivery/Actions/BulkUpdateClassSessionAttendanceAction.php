<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;

final class BulkUpdateClassSessionAttendanceAction
{
    /**
     * @param  array{class_session: ClassSession, attendance_ids: array<int, int>, status: string}  $data
     */
    public static function run(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $classSession = $data['class_session'];
            $attendanceIds = $data['attendance_ids'];
            $status = $data['status'];

            $updated = $classSession->attendances()
                ->whereIn('id', $attendanceIds)
                ->update(['status' => $status]);

            $classSession->updateAttendanceStatistics();

            return $updated;
        });
    }
}
