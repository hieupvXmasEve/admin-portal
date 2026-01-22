<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListBillingExceptionsQuery
{
    public function handle(?int $semesterId, ?string $type, string $path): LengthAwarePaginator
    {
        // Placeholder data logic from original controller
        $exceptionsData = collect([]);

        // In a real implementation effectively:
        // switch($type) { ... query ... }

        return new LengthAwarePaginator(
            $exceptionsData,
            0,
            20,
            1,
            ['path' => $path, 'pageName' => 'page']
        );
    }
}
