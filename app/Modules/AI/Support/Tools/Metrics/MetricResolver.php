<?php

declare(strict_types=1);

namespace App\Modules\AI\Support\Tools\Metrics;

use App\Modules\AI\Support\Tools\QueryMetricsExecutionContext;

interface MetricResolver
{
    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $metric
     * @return array<string, mixed>
     */
    public function resolve(array $validation, array $metric, QueryMetricsExecutionContext $context): array;
}
