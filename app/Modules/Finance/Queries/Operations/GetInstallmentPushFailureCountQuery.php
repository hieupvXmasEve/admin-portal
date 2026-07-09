<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\FinanceChargeInstallment;

class GetInstallmentPushFailureCountQuery
{
    /** @return array{count:int,total_amount:float,oldest_at:?string} */
    public function handle(?int $semesterId = null): array
    {
        $base = FinanceChargeInstallment::query()
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->whereNotNull('last_push_error');

        if ($semesterId !== null) {
            $base->whereHas('charge', fn ($q) => $q->where('semester_id', $semesterId));
        }

        return [
            'count' => (clone $base)->count(),
            'total_amount' => (float) (clone $base)->sum('amount'),
            'oldest_at' => (clone $base)->orderBy('last_push_attempted_at')->value('last_push_attempted_at')?->toIso8601String(),
        ];
    }
}
