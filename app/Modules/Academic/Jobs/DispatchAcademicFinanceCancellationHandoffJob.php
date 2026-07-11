<?php

declare(strict_types=1);

namespace App\Modules\Academic\Jobs;

use App\Modules\Academic\Actions\DispatchAcademicFinanceCancellationHandoffAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchAcademicFinanceCancellationHandoffJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $handoffId) {}

    public function handle(DispatchAcademicFinanceCancellationHandoffAction $action): void
    {
        $action->handle($this->handoffId);
    }
}
