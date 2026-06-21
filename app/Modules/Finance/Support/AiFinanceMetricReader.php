<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery;
use App\Modules\Finance\Queries\Reporting\ListDngLifecycleQuery;
use App\Modules\Finance\Queries\Reporting\ListFeeMonitorQuery;
use App\Shared\Contracts\Finance\AiFinanceMetricReader as AiFinanceMetricReaderContract;

class AiFinanceMetricReader implements AiFinanceMetricReaderContract
{
    public function __construct(
        private readonly ListCollectionProgressQuery $collectionProgressQuery,
        private readonly ListFeeMonitorQuery $feeMonitorQuery,
        private readonly ListDngLifecycleQuery $dngLifecycleQuery,
    ) {}

    public function collectionProgress(int $semesterId, array $filters = []): array
    {
        return $this->collectionProgressQuery->handle($semesterId, $filters);
    }

    public function feeMonitor(int $semesterId, array $filters = []): array
    {
        return $this->feeMonitorQuery->handle($semesterId, $filters);
    }

    public function dngLifecycle(?int $selectedSemesterId, array $filters = []): array
    {
        return $this->dngLifecycleQuery->handle($selectedSemesterId, $filters);
    }
}
