<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
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

it('uses a semester scope badge when all-campus operators pass a semester id', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();

    $scoped = app(GetFinanceCockpitDataHealthQuery::class)->handle($campus->id, true, $semester->id);
    $global = app(GetFinanceCockpitDataHealthQuery::class)->handle($campus->id, true);

    expect($scoped['scope_badge'])->toBe('semester')
        ->and($global['scope_badge'])->toBe('all_campus');
});
