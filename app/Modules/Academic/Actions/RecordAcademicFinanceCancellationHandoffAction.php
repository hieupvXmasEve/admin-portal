<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Jobs\DispatchAcademicFinanceCancellationHandoffJob;
use App\Modules\Academic\Models\AcademicFinanceCancellationHandoff;
use Illuminate\Support\Facades\DB;

/**
 * Persist (and schedule) the Academic → Finance cancellation handoff.
 * Must run inside the same DB transaction that marks the source pending.
 */
class RecordAcademicFinanceCancellationHandoffAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(
        string $sourceSystem,
        string $sourceKind,
        string $sourceRef,
        string $obligationType,
        string $unpaidVoidReason,
        string $paidVoidReason,
        ?int $actorUserId,
        array $payload,
    ): AcademicFinanceCancellationHandoff {
        $handoff = AcademicFinanceCancellationHandoff::query()->firstOrCreate(
            [
                'source_system' => $sourceSystem,
                'source_kind' => $sourceKind,
                'source_ref' => $sourceRef,
                'obligation_type' => $obligationType,
            ],
            [
                'unpaid_void_reason' => $unpaidVoidReason,
                'paid_void_reason' => $paidVoidReason,
                'actor_user_id' => $actorUserId,
                'payload' => $payload,
                'status' => AcademicFinanceCancellationHandoff::STATUS_PENDING,
            ],
        );

        // Re-drive failed/pending handoffs on source re-entry.
        if ($handoff->status !== AcademicFinanceCancellationHandoff::STATUS_DISPATCHED) {
            $handoff->update([
                'unpaid_void_reason' => $unpaidVoidReason,
                'paid_void_reason' => $paidVoidReason,
                'actor_user_id' => $actorUserId,
                'payload' => $payload,
                'status' => AcademicFinanceCancellationHandoff::STATUS_PENDING,
                'last_error' => null,
            ]);
        }

        // afterCommit is relative to the outer Academic transaction when present.
        DispatchAcademicFinanceCancellationHandoffJob::dispatch($handoff->id)->afterCommit();

        return $handoff->fresh() ?? $handoff;
    }

    /**
     * @param  array{
     *   source_system: string,
     *   source_kind: string,
     *   source_ref: string,
     *   obligation_type: string,
     *   unpaid_void_reason: string,
     *   paid_void_reason: string,
     *   actor_user_id?: int|null,
     *   payload?: array<string, mixed>
     * }  $data
     */
    public static function run(array $data): AcademicFinanceCancellationHandoff
    {
        return app(self::class)->handle(
            $data['source_system'],
            $data['source_kind'],
            $data['source_ref'],
            $data['obligation_type'],
            $data['unpaid_void_reason'],
            $data['paid_void_reason'],
            $data['actor_user_id'] ?? null,
            $data['payload'] ?? [],
        );
    }
}
