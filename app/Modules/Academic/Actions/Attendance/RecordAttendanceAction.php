<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Attendance;

use App\Models\Attendance;

/** @deprecated Resolve the Delivery action instead. */
class RecordAttendanceAction
{
    public const STATUSES = \App\Modules\Academic\Delivery\Actions\RecordAttendanceAction::STATUSES;

    public const RECORDING_METHODS = \App\Modules\Academic\Delivery\Actions\RecordAttendanceAction::RECORDING_METHODS;

    /** @param array<string, mixed> $data */
    public static function run(array $data): Attendance
    {
        return \App\Modules\Academic\Delivery\Actions\RecordAttendanceAction::run($data);
    }
}
