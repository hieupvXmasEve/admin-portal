<?php

declare(strict_types=1);

namespace App\Modules\Finance\Jobs;

use App\Modules\Finance\Actions\ProcessFinanceCancellationOperationAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFinanceCancellationOperationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $operationId) {}

    public function handle(ProcessFinanceCancellationOperationAction $action): void
    {
        $action->handle($this->operationId);
    }
}
