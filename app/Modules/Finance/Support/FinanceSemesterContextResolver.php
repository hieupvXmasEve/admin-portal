<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Support\SemesterContextResolver;

/**
 * Finance compatibility wrapper for the global operator semester context.
 */
final class FinanceSemesterContextResolver
{
    public static function selectedId(): ?int
    {
        return SemesterContextResolver::selectedId();
    }
}
