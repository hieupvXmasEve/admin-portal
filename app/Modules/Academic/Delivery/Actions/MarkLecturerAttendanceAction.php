<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Modules\Academic\Delivery\Support\LecturerAttendanceService;

final class MarkLecturerAttendanceAction
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function run(array $data): array
    {
        return app(LecturerAttendanceService::class)->markAttendance($data['lecturer_id'], $data['session_id'], $data['attendance_data']);
    }
}
