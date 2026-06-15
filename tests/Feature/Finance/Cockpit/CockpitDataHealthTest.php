<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitDataHealthQuery;

it('returns a data_health payload with invariants and balance-match metric', function () {
    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    $result = app(GetFinanceCockpitDataHealthQuery::class)->handle($campus->id, false);

    expect($result)->toHaveKeys(['scope_badge', 'critical_count', 'invariants', 'balance_match']);
    expect($result['scope_badge'])->toBe('campus');
    expect($result['balance_match'])->toHaveKeys(['match_pct', 'matched', 'denominator']);
    expect($result['invariants'])->toBeArray();
});

it('uses an all-system scope badge for all-campus scope', function () {
    $campus = Campus::factory()->create();

    $result = app(GetFinanceCockpitDataHealthQuery::class)->handle($campus->id, true);

    expect($result['scope_badge'])->toBe('all_campus');
});