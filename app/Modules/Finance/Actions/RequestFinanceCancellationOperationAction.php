<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Jobs\ProcessFinanceCancellationOperationJob;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationOperationRequestResult;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use Illuminate\Support\Facades\DB;

class RequestFinanceCancellationOperationAction implements FinanceCancellationOperationRequestContract
{
    /** @param array<string, mixed> $payload */
    public function request(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        string $unpaidVoidReason,
        string $paidVoidReason,
        ?int $actorUserId,
        array $payload,
    ): FinanceCancellationOperationRequestResult {
        return DB::transaction(function () use (
            $sourceSystem,
            $sourceKind,
            $sourceRef,
            $obligationType,
            $unpaidVoidReason,
            $paidVoidReason,
            $actorUserId,
            $payload,
        ): FinanceCancellationOperationRequestResult {
            $operation = FinanceCancellationOperation::query()->firstOrCreate(
                [
                    'source_system' => $sourceSystem,
                    'source_kind' => $sourceKind,
                    'source_ref' => $sourceRef,
                    'obligation_type' => $obligationType,
                ],
                [
                    'source_system' => $sourceSystem,
                    'source_kind' => $sourceKind,
                    'source_ref' => $sourceRef,
                    'obligation_type' => $obligationType,
                    'status' => FinanceCancellationOperation::STATUS_REQUESTED,
                    'unpaid_void_reason' => $unpaidVoidReason,
                    'paid_void_reason' => $paidVoidReason,
                    'actor_user_id' => $actorUserId,
                    'source_payload' => $payload,
                ],
            );

            // Idempotent create: only the first writer schedules processing.
            // afterCommit keeps dispatch outside the Finance transaction.
            if ($operation->wasRecentlyCreated) {
                ProcessFinanceCancellationOperationJob::dispatch($operation->id)->afterCommit();
            }

            return new FinanceCancellationOperationRequestResult($operation->id, $operation->status);
        });
    }

    /** @param array<string, mixed> $data */
    public static function run(array $data): FinanceCancellationOperationRequestResult
    {
        return app(self::class)->request(...$data);
    }
}
