<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class DeleteClassSessionAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): bool
    {
        $classSession = ClassSession::query()->findOrFail($data['class_session_id']);

        return app(ClassSessionService::class)->deleteClassSession($classSession);
    }
}
