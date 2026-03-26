<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Jobs\ReconcileDngPaymentsJob;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the configured DNG campus code for reconciliation', function () {
    config(['services.dng.campus_code' => 'FAUHN']);

    $service = Mockery::mock(DngReconciliationService::class);
    $service->shouldReceive('reconcileDay')
        ->once()
        ->with('FAUHN', '2026-03-26 22:30:00')
        ->andReturn([
            'backfilled' => 0,
            'up_to_date' => 0,
            'orphans' => 0,
            'errors' => 0,
        ]);

    $job = new ReconcileDngPaymentsJob('2026-03-26 22:30:00');
    $job->handle($service);
});

it('skips reconciliation when no DNG campus code is configured', function () {
    config(['services.dng.campus_code' => '']);

    $service = Mockery::mock(DngReconciliationService::class);
    $service->shouldNotReceive('reconcileDay');

    $job = new ReconcileDngPaymentsJob('2026-03-26 22:30:00');
    $job->handle($service);
});
