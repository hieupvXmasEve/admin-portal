<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class GenerateClassSessionAttendanceAction
{
    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function run(array $data): array
    {
        $classSession = ClassSession::query()->findOrFail($data['class_session_id']);

        return app(ClassSessionService::class)->generateAttendanceForSession($classSession);
    }
}
