<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class BulkDeleteWebClassSessionsAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): int
    {
        $count = 0;
        foreach ($data['ids'] as $sessionId) {
            $classSession = ClassSession::query()->find($sessionId);
            if ($classSession !== null) {
                app(ClassSessionService::class)->deleteClassSession($classSession);
                $count++;
            }
        }

        return $count;
    }
}
