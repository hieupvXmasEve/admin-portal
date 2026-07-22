<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

final class GetImpersonationSessionsQuery
{
    public function handle(array $administrator): array
    {
        return ['active_sessions' => [], 'recent_impersonations' => [], 'total_impersonations_today' => 0];
    }
}
