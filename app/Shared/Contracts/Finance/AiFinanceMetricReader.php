<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface AiFinanceMetricReader
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function collectionProgress(int $semesterId, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function feeMonitor(int $semesterId, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dngLifecycle(?int $selectedSemesterId, array $filters = []): array;
}
