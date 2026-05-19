<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Jobs;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileDngPaymentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        protected ?string $date = null,
    ) {
        $this->onQueue('reconciliation');
    }

    public function handle(
        DngReconciliationService $reconciliationService,
        DngCampusCodeResolver $dngCampusCodeResolver,
    ): void {
        $date = $this->date ?? now()->format('Y-m-d H:i:s');
        $campusCodes = $dngCampusCodeResolver->allConfiguredCampusCodes();

        $totalSummary = ['backfilled' => 0, 'up_to_date' => 0, 'orphans' => 0, 'errors' => 0];

        foreach ($campusCodes as $campusCode) {
            $result = $reconciliationService->reconcileDay($campusCode, $date);

            foreach ($result as $key => $value) {
                $totalSummary[$key] += $value;
            }
        }

        // Alert on stale pending requests (>2 hours old)
        $staleCount = DngPaymentRequest::stale(120)->count();
        if ($staleCount > 0) {
            Log::warning('DNG reconciliation: stale pending requests detected', [
                'stale_count' => $staleCount,
                'threshold_minutes' => 120,
            ]);
        }

        // Alert on long-standing paid_uninvoiced (>24 hours)
        $uninvoicedCount = DngPaymentRequest::awaitingInvoice()
            ->where('paid_at', '<', now()->subHours(24))
            ->count();
        if ($uninvoicedCount > 0) {
            Log::warning('DNG reconciliation: long-standing paid_uninvoiced requests', [
                'count' => $uninvoicedCount,
                'threshold_hours' => 24,
            ]);
        }

        Log::info('DNG reconciliation job complete', [
            'date' => $date,
            'campus_count' => count($campusCodes),
            'summary' => $totalSummary,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DNG reconciliation job failed', [
            'date' => $this->date,
            'error' => $exception->getMessage(),
        ]);
    }
}
