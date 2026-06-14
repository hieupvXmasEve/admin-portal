<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Support\BillingExceptionCollector;

class GetBillingExceptionCountsQuery
{
    public function handle(?int $semesterId): array
    {
        $campusId = app('campus')?->id;

        return app(BillingExceptionCollector::class)->counts($semesterId, $campusId);
    }
}