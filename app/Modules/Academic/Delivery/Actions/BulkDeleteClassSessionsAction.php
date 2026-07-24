<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class BulkDeleteClassSessionsAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): int
    {
        $count = 0;
        foreach ($data['ids'] as $sessionId) {
            $session = ClassSession::query()->find($sessionId);
            if ($session !== null) {
                app(ClassSessionService::class)->deleteClassSession($session);
                $count++;
            }
        }

        return $count;
    }
}
