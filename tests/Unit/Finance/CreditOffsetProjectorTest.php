<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceSetting;
use App\Modules\Finance\Support\CreditOffsetProjector;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;

function makeCreditOffsetSettings(bool $enabled, float $minBalance): FinanceSetting
{
    return new FinanceSetting([
        'credit_offset_enabled' => $enabled,
        'credit_offset_min_balance' => $minBalance,
    ]);
}

// StudentFinanceSettlementPositionReader is final (can't be mocked); project()
// never touches it, so a real container-built instance is a harmless collaborator.
function makeCreditOffsetProjector(): CreditOffsetProjector
{
    return new CreditOffsetProjector(app(StudentFinanceSettlementPositionReader::class));
}

it('projects zero when the setting is disabled', function () {
    $result = makeCreditOffsetProjector()->project(20_000_000, 45_000_000, makeCreditOffsetSettings(false, 0));

    expect($result)->toBe(['unapplied_credit' => 20_000_000.0, 'credit_offset_projected' => 0.0]);
});

it('projects zero when the unapplied cash is below the minimum balance', function () {
    $result = makeCreditOffsetProjector()->project(5_000_000, 45_000_000, makeCreditOffsetSettings(true, 10_000_000));

    expect($result)->toBe(['unapplied_credit' => 5_000_000.0, 'credit_offset_projected' => 0.0]);
});

it('projects the full unapplied cash when it is below the net amount', function () {
    $result = makeCreditOffsetProjector()->project(20_000_000, 45_000_000, makeCreditOffsetSettings(true, 10_000_000));

    expect($result)->toBe(['unapplied_credit' => 20_000_000.0, 'credit_offset_projected' => 20_000_000.0]);
});

it('caps the projected offset at the net amount when unapplied cash exceeds it', function () {
    $result = makeCreditOffsetProjector()->project(60_000_000, 45_000_000, makeCreditOffsetSettings(true, 10_000_000));

    expect($result)->toBe(['unapplied_credit' => 60_000_000.0, 'credit_offset_projected' => 45_000_000.0]);
});

it('projects zero when there is no unapplied cash at all', function () {
    $result = makeCreditOffsetProjector()->project(0.0, 45_000_000, makeCreditOffsetSettings(true, 0));

    expect($result)->toBe(['unapplied_credit' => 0.0, 'credit_offset_projected' => 0.0]);
});
