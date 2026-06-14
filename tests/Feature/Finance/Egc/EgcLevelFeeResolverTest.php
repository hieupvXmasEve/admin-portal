<?php

declare(strict_types=1);

use App\Models\Unit;
use App\Modules\Finance\Support\EgcLevelFeeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-06: EGC level fee = Unit.base_fee for the level, with a flat fallback when
 * no unit / no base_fee exists. Same resolver feeds preview and both execute
 * paths so they can never diverge.
 */
it('uses the unit base_fee when an egc unit exists for the level', function () {
    Unit::create([
        'code' => 'EGC-L2',
        'name' => 'EGC Level 2',
        'credit_points' => 0,
        'retake_fee' => 0,
        'unit_type' => 'egc',
        'level' => 2,
        'base_fee' => 12_000_000,
    ]);

    expect((new EgcLevelFeeResolver)->resolve(2))->toBe(12_000_000.0);
});

it('falls back to the flat fee when no egc unit exists for the level', function () {
    expect((new EgcLevelFeeResolver)->resolve(3))
        ->toBe((float) EgcLevelFeeResolver::FALLBACK_FEE);
});

it('falls back to the flat fee when the unit base_fee is zero or null', function () {
    Unit::create([
        'code' => 'EGC-L4',
        'name' => 'EGC Level 4',
        'credit_points' => 0,
        'retake_fee' => 0,
        'unit_type' => 'egc',
        'level' => 4,
        'base_fee' => 0,
    ]);

    expect((new EgcLevelFeeResolver)->resolve(4))
        ->toBe((float) EgcLevelFeeResolver::FALLBACK_FEE);
});
