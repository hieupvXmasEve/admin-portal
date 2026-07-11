<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Models\AcademicFinanceCancellationHandoff;
use App\Shared\Contracts\Finance\FinanceCancellationOperationRequestContract;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Delivers a durable Academic cancellation handoff into Finance.
 * Idempotent: Finance firstOrCreates by source quad.
 */
class DispatchAcademicFinanceCancellationHandoffAction
{
    public function __construct(
        private readonly FinanceCancellationOperationRequestContract $financeCancellationRequest,
    ) {}

    public function handle(int $handoffId): void
    {
        $handoff = AcademicFinanceCancellationHandoff::query()->find($handoffId);
        if ($handoff === null || $handoff->status === AcademicFinanceCancellationHandoff::STATUS_DISPATCHED) {
            return;
        }

        try {
            $this->financeCancellationRequest->request(
                $handoff->source_system,
                $handoff->source_kind,
                $handoff->source_ref,
                $handoff->obligation_type,
                $handoff->unpaid_void_reason,
                $handoff->paid_void_reason,
                $handoff->actor_user_id === null ? null : (int) $handoff->actor_user_id,
                $handoff->payload ?? [],
            );

            DB::transaction(function () use ($handoffId): void {
                $locked = AcademicFinanceCancellationHandoff::query()->lockForUpdate()->find($handoffId);
                if ($locked === null || $locked->status === AcademicFinanceCancellationHandoff::STATUS_DISPATCHED) {
                    return;
                }

                $locked->update([
                    'status' => AcademicFinanceCancellationHandoff::STATUS_DISPATCHED,
                    'dispatched_at' => now(),
                    'last_error' => null,
                    'attempts' => (int) $locked->attempts + 1,
                ]);
            });
        } catch (Throwable $exception) {
            $row = AcademicFinanceCancellationHandoff::query()->find($handoffId);
            if ($row !== null) {
                $row->update([
                    'status' => AcademicFinanceCancellationHandoff::STATUS_FAILED,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'attempts' => (int) $row->attempts + 1,
                ]);
            }

            throw $exception;
        }
    }

    /** @param array{handoff_id: int} $data */
    public static function run(array $data): void
    {
        app(self::class)->handle((int) $data['handoff_id']);
    }
}
