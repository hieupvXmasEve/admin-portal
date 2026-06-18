<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use Illuminate\Support\Collection;

class GetFeeMonitorSummaryQuery
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function fromRows(Collection $rows): array
    {
        return [
            'missing_count' => $this->countByGenerationState($rows, 'missing'),
            'generated_count' => $this->countByGenerationState($rows, 'generated'),
            'skipped_count' => $this->countByGenerationState($rows, 'skipped'),
            'voided_count' => $this->countByGenerationState($rows, 'voided'),
            'blocked_count' => $this->countByGenerationState($rows, 'blocked'),
            'paid_count' => $this->countByPaymentState($rows, 'paid'),
            'partially_paid_count' => $this->countByPaymentState($rows, 'partially_paid'),
            'outstanding_count' => $this->countByPaymentState($rows, 'outstanding'),
            'total_count' => $rows->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function handle(int $semesterId, array $filters = []): array
    {
        return $this->fromRows(app(ListFeeMonitorQuery::class)->collectRows($semesterId, $filters));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countByGenerationState(Collection $rows, string $state): int
    {
        return $rows->where('generation_state', $state)->count();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countByPaymentState(Collection $rows, string $state): int
    {
        return $rows->where('payment_state', $state)->count();
    }
}