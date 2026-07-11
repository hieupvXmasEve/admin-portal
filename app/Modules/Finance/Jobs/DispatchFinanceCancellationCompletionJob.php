<?php

declare(strict_types=1);

namespace App\Modules\Finance\Jobs;

use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationCompletionData;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchFinanceCancellationCompletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $outboxId) {}

    public function handle(FinanceCancellationCompletionContract $consumer): void
    {
        $outbox = FinanceCancellationCompletionOutbox::query()->with('operation')->find($this->outboxId);
        if ($outbox === null || $outbox->status === FinanceCancellationCompletionOutbox::STATUS_DISPATCHED) {
            return;
        }

        $operation = $outbox->operation;
        if ($operation === null) {
            return;
        }

        $consumer->complete(new FinanceCancellationCompletionData(
            $operation->source_system,
            $operation->source_kind,
            $operation->source_ref,
            $operation->obligation_type,
            $operation->result_payload ?? [],
        ));
        $outbox->update(['status' => FinanceCancellationCompletionOutbox::STATUS_DISPATCHED, 'dispatched_at' => now()]);
    }
}
