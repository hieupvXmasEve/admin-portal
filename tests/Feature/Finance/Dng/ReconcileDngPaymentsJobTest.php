<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Finance\Dng\Jobs\ReconcileDngPaymentsJob;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses configured campus dng codes for reconciliation', function () {
    Campus::factory()->create(['dng_code' => 'FAUHN']);

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
    $job->handle($service, app(DngCampusCodeResolver::class));
});

it('skips reconciliation when no campus dng code is configured', function () {
    Campus::factory()->create(['dng_code' => null]);

    $service = Mockery::mock(DngReconciliationService::class);
    $service->shouldNotReceive('reconcileDay');

    $job = new ReconcileDngPaymentsJob('2026-03-26 22:30:00');
    $job->handle($service, app(DngCampusCodeResolver::class));
});
