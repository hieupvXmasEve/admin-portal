<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Jobs\ProcessFinanceCancellationOperationJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;

class RecoverFinanceCancellationWorkAction
{
    /** @return array{operation_ids: list<int>, completion_outbox_ids: list<int>} */
    public function handle(int $limit = 100): array
    {
        $staleBefore = now()->subSeconds(ProcessFinanceCancellationOperationAction::PROCESSING_CLAIM_STALE_SECONDS);
        $operationIds = FinanceCancellationOperation::query()
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', FinanceCancellationOperation::STATUS_REQUESTED)
                    ->orWhere(function ($processing) use ($staleBefore): void {
                        $processing->where('status', FinanceCancellationOperation::STATUS_PROCESSING)
                            ->where(function ($claim) use ($staleBefore): void {
                                $claim->whereNull('processing_claimed_at')
                                    ->orWhere('processing_claimed_at', '<', $staleBefore);
                            });
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $completionOutboxIds = FinanceCancellationCompletionOutbox::query()
            ->where('status', FinanceCancellationCompletionOutbox::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($operationIds as $id) {
            ProcessFinanceCancellationOperationJob::dispatch($id);
        }
        foreach ($completionOutboxIds as $id) {
            DispatchFinanceCancellationCompletionJob::dispatch($id);
        }

        return ['operation_ids' => $operationIds, 'completion_outbox_ids' => $completionOutboxIds];
    }

    /** @param array{limit?: int} $data @return array{operation_ids: list<int>, completion_outbox_ids: list<int>} */
    public static function run(array $data): array
    {
        return app(self::class)->handle((int) ($data['limit'] ?? 100));
    }
}
