<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Attendance;

use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;

class BulkUpdateClassSessionAttendanceAction
{
    /**
     * @param  array<int, int>  $attendanceIds
     */
    public static function run(ClassSession $classSession, array $attendanceIds, string $status): int
    {
        return DB::transaction(function () use ($classSession, $attendanceIds, $status): int {
            $updated = $classSession->attendances()
                ->whereIn('id', $attendanceIds)
                ->update(['status' => $status]);

            $classSession->updateAttendanceStatistics();

            return $updated;
        });
    }
}
